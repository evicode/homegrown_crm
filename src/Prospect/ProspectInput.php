<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Prospect;

use Dreamsmith\Campaign\Records\Normalizer;

final class ProspectInput
{
    public function __construct(public readonly ?int $companyId,public readonly ?int $contactId,public readonly string $segment,public readonly ?string $source,public readonly ?string $whyThem,public readonly ?string $businessProblem,public readonly ?string $qualificationNotes,public readonly bool $problem,public readonly bool $timing,public readonly bool $buyer,public readonly bool $budget){}
    /** @param array<string,mixed> $v @param array<string,mixed> $sales @return array{self|null,array<string,string>} */
    public static function fromArray(array $v,array $sales):array{
        $e=[];$company=self::id($v['company_id']??null);$contact=self::id($v['primary_contact_id']??null);
        if($company===false)$e['company_id']='Select a valid company.';if($contact===false)$e['primary_contact_id']='Select a valid contact.';
        if(($company===null||$company===false)&&($contact===null||$contact===false))$e['identity']='Select a company, contact, or both.';
        $segment=is_string($v['segment']??null)?$v['segment']:'';if(!isset($sales['segments'][$segment]))$e['segment']='Select a valid segment.';
        $text=[];foreach(['source'=>255,'why_them'=>10000,'business_problem'=>10000,'qualification_notes'=>10000] as $k=>$limit){$text[$k]=Normalizer::text($v[$k]??null);if($text[$k]!==null&&strlen($text[$k])>$limit)$e[$k]='This field is too long.';}
        if($e!==[])return[null,$e];
        return[new self($company?:null,$contact?:null,$segment,$text['source'],$text['why_them'],$text['business_problem'],$text['qualification_notes'],($v['problem_understood']??null)==='1',($v['timing_understood']??null)==='1',($v['buyer_understood']??null)==='1',($v['budget_plausible']??null)==='1'),[]];
    }
    private static function id(mixed $v):int|false|null{return $v===null||$v===''?null:filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);}
}
