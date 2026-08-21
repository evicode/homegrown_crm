<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Data;

use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Prospect\ProspectInput;
use Dreamsmith\Campaign\Prospect\ProspectService;
use Dreamsmith\Campaign\Records\CompanyInput;
use Dreamsmith\Campaign\Records\ContactInput;
use Dreamsmith\Campaign\Records\RecordService;
use Dreamsmith\Campaign\Security\SessionManager;
use PDO;

final class DataService
{
    private const HEADERS = ['segment','company_name','first_name','last_name','email','source','why_them','signal_key','evidence_note'];
    public function __construct(private readonly Database $database, private readonly SessionManager $session, private readonly RecordService $records, private readonly ProspectService $prospects, private readonly array $sales, private readonly string $signingKey) {}
    /** @return list<string> */ public function headers(): array { return self::HEADERS; }
    /** @param array<string,mixed>|null $file @return array<string,mixed> */
    public function preview(?array $file, int $ownerId): array
    {
        if ($file === null || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) throw new \InvalidArgumentException('Choose a CSV file to preview.');
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 2_000_000) throw new \InvalidArgumentException('CSV files must be between 1 byte and 2 MB.');
        $raw = file_get_contents($file['tmp_name']); if (!is_string($raw) || preg_match('//u', $raw) !== 1) throw new \InvalidArgumentException('The file must be UTF-8 encoded.');
        $stream = fopen('php://temp', 'r+'); if ($stream === false) throw new \RuntimeException('Unable to read the upload.'); fwrite($stream, $raw); rewind($stream);
        $headers = fgetcsv($stream); if (!is_array($headers)) throw new \InvalidArgumentException('The CSV has no header row.');
        $headers = array_map(static fn ($v): string => trim((string)$v), $headers); $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        if ($headers !== self::HEADERS || count($headers) !== count(array_unique($headers))) throw new \InvalidArgumentException('Use the downloaded CSV template without changing its headers.');
        $rows=[]; $seen=[]; $number=1;
        while (($row=fgetcsv($stream)) !== false) { $number++; if (count($row) !== count($headers)) { $rows[]=['line'=>$number,'errors'=>['Expected '.count($headers).' columns.'],'data'=>[]]; continue; } if (count($rows)>=500) throw new \InvalidArgumentException('A preview is limited to 500 rows.'); $data=array_combine($headers,array_map(static fn($v):string=>trim((string)$v),$row)); $errors=$this->validateRow($data,$seen); $rows[]=['line'=>$number,'errors'=>$errors,'data'=>$data]; }
        fclose($stream); if ($rows===[]) throw new \InvalidArgumentException('The CSV has no data rows.');
        $valid=array_values(array_filter($rows,static fn(array $row):bool=>$row['errors']===[])); $id=bin2hex(random_bytes(16)); $expires=time()+900; $hash=hash('sha256',json_encode($valid,JSON_THROW_ON_ERROR)); $token=$this->token($id,$ownerId,$hash,$expires);
        $this->session->set('csv_import_preview',['id'=>$id,'owner'=>$ownerId,'expires'=>$expires,'hash'=>$hash,'token'=>$token,'rows'=>$valid,'invalid_count'=>count($rows)-count($valid)]);
        return ['rows'=>$rows,'valid_count'=>count($valid),'invalid_count'=>count($rows)-count($valid),'token'=>$token,'expires'=>$expires];
    }
    public function commit(string $token,int $ownerId,string $correlationId): int
    {
        $plan=$this->session->get('csv_import_preview'); if (!is_array($plan) || ($plan['owner']??null)!==$ownerId || !is_string($plan['token']??null) || !hash_equals($plan['token'],$token) || (int)($plan['expires']??0)<time() || !hash_equals((string)$plan['token'],$this->token((string)$plan['id'],$ownerId,(string)$plan['hash'],(int)$plan['expires']))) throw new \DomainException('This import preview is invalid or has expired. Upload the file again.');
        $rows=$plan['rows']??[]; if ((int)($plan['invalid_count']??0)>0) throw new \DomainException('Correct every rejected row before committing an import.'); if (!is_array($rows)||$rows===[]) throw new \DomainException('There are no valid rows to import.');
        $this->database->transaction(function(PDO $pdo)use($rows,$ownerId,$correlationId):void{$companies=[];foreach($rows as $row){$this->createRow($pdo,$row['data'],$ownerId,$correlationId,$companies);}}); $this->session->remove('csv_import_preview'); return count($rows);
    }
    public function cancel():void{$this->session->remove('csv_import_preview');}
    /** @return array{filename:string,body:string} */ public function export(string $type):array
    {
        $queries=['prospects'=>["SELECT p.id,p.segment,p.status,c.name company_name,CONCAT_WS(' ',ct.first_name,ct.last_name) contact_name,p.source,p.why_them,p.business_problem,p.qualification_notes,p.archived_at,p.created_at FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id ORDER BY p.id",['id','segment','status','company_name','contact_name','source','why_them','business_problem','qualification_notes','archived_at','created_at']], 'interactions'=>["SELECT i.id,i.prospect_id,i.type,i.direction,i.occurred_at,i.outcome,i.summary,i.voided_at FROM interactions i ORDER BY i.id",['id','prospect_id','type','direction','occurred_at','outcome','summary','voided_at']], 'opportunities'=>["SELECT o.id,o.prospect_id,o.offer_key,o.stage,o.value_amount,o.currency,o.expected_close_on,o.closed_at,o.lost_reason FROM opportunities o ORDER BY o.id",['id','prospect_id','offer_key','stage','value_amount','currency','expected_close_on','closed_at','lost_reason']]]; if(!isset($queries[$type]))throw new \InvalidArgumentException('Choose a valid export.'); [$sql,$headers]=$queries[$type];$rows=$this->database->pdo()->query($sql)->fetchAll();$out=fopen('php://temp','r+');fputcsv($out,$headers);foreach($rows as $row)fputcsv($out,array_map([$this,'safeCell'],array_map(static fn(string $header)=>$row[$header]??'', $headers)));rewind($out);$body=(string)stream_get_contents($out);fclose($out);return['filename'=>$type.'-'.gmdate('Y-m-d').'.csv','body'=>$body];
    }
    /** @param array<string,string> $data @param array<string,bool> $seen @return list<string> */ private function validateRow(array $data,array &$seen):array{$errors=[];$key=strtolower($data['company_name'].'|'.$data['email'].'|'.$data['first_name'].'|'.$data['last_name']);if(isset($seen[$key]))$errors[]='Duplicate of an earlier row.';$seen[$key]=true;if($data['company_name']===''&&$data['first_name']===''&&$data['last_name']==='')$errors[]='Provide a company or contact name.';if(!isset($this->sales['segments'][$data['segment']]))$errors[]='Choose a valid segment.';if($data['email']!==''&&filter_var($data['email'],FILTER_VALIDATE_EMAIL)===false)$errors[]='Email is invalid.';if(($data['signal_key']==='')!==($data['evidence_note']===''))$errors[]='Signal key and evidence note must be provided together.';if($data['signal_key']!==''&&!isset($this->sales['signals'][$data['signal_key']]))$errors[]='Signal key is invalid.';foreach($data as $value)if(strlen($value)>4000)$errors[]='A cell exceeds the allowed length.';return array_values(array_unique($errors));}
    /** @param array<string,string> $data @param array<string,int> $companies */ private function createRow(PDO $pdo,array $data,int $ownerId,string $correlationId,array &$companies):void{$companyId=null;$contactId=null;if($data['company_name']!==''){[$company,$errors]=CompanyInput::fromArray(['name'=>$data['company_name']]);if($company===null)throw new \DomainException(implode(' ',$errors));$companyId=$companies[$company->normalizedName]??null;if($companyId===null){$companyId=$this->records->createCompany($company,false,$ownerId,$correlationId);$companies[$company->normalizedName]=$companyId;}}if($data['first_name']!==''||$data['last_name']!==''||$data['email']!==''){[$contact,$errors]=ContactInput::fromArray(['company_id'=>$companyId,'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'email'=>$data['email']]);if($contact===null)throw new \DomainException(implode(' ',$errors));$contactId=$this->records->createContact($contact,false,$ownerId,$correlationId);}[$prospect,$errors]=ProspectInput::fromArray(['company_id'=>$companyId,'primary_contact_id'=>$contactId,'segment'=>$data['segment'],'source'=>$data['source'],'why_them'=>$data['why_them']],$this->sales);if($prospect===null)throw new \DomainException(implode(' ',$errors));$prospectId=$this->prospects->create($prospect,$ownerId,$correlationId);if($data['signal_key']!==''){$s=$pdo->prepare('SELECT id FROM signals WHERE signal_key=:key');$s->execute(['key'=>$data['signal_key']]);$signal=(int)$s->fetchColumn();$this->prospects->addSignal($prospectId,$signal,$data['evidence_note'],null,1,$ownerId,$correlationId);}}
    private function token(string $id,int $owner,string $hash,int $expires):string{return hash_hmac('sha256',$id.'|'.$owner.'|'.$hash.'|'.$expires,$this->signingKey);}
    private function safeCell(mixed $value):string{$value=(string)$value;return preg_match('/^[=+\-@]/',$value)?"'".$value:$value;}
}
