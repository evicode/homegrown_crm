<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Interaction;
use DateTimeImmutable;use DateTimeZone;use Dreamsmith\Campaign\Records\Normalizer;
final class InteractionInput{
 public function __construct(public readonly ?int $contactId,public readonly string $type,public readonly string $direction,public readonly DateTimeImmutable $occurredAt,public readonly string $summary,public readonly string $outcome,public readonly bool $qualifiesContact,public readonly bool $qualifiesResponse){}
 /** @param array<string,mixed> $v @param array<string,mixed> $sales @return array{self|null,array<string,string>} */
 public static function fromArray(array $v,array $sales,string $timezone,DateTimeImmutable $now,int $backdateDays=365):array{
  $e=[];$type=is_string($v['type']??null)?$v['type']:'';$direction=is_string($v['direction']??null)?$v['direction']:'';$outcome=is_string($v['outcome']??null)?$v['outcome']:'';
  if(!isset($sales['interaction_types'][$type]))$e['type']='Select a valid interaction type.';
  if(!isset($sales['interaction_directions'][$direction]))$e['direction']='Select a valid direction.';
  if(!isset($sales['interaction_outcomes'][$outcome]))$e['outcome']='Select a valid outcome.';
  if($type==='note'&&($direction!=='internal'||$outcome!=='note_only'))$e['outcome']='Internal notes must use internal direction and note-only outcome.';
  if($type!=='note'&&$direction==='internal')$e['direction']='Only notes may use internal direction.';
  $summary=Normalizer::text($v['summary']??null);if($summary===null||strlen($summary)>10000)$e['summary']='Enter a summary no longer than 10,000 characters.';
  $raw=is_string($v['occurred_at']??null)?$v['occurred_at']:'';$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone($timezone));$errors=DateTimeImmutable::getLastErrors();
  if($date===false||($errors!==false&&($errors['warning_count']||$errors['error_count'])))$e['occurred_at']='Enter a valid occurrence time.';elseif($date<$now->modify("-{$backdateDays} days")||$date>$now->modify('+5 minutes'))$e['occurred_at']='Occurrence time is outside the allowed range.';
  $contact=$v['contact_id']??null;$contact=$contact===null||$contact===''?null:filter_var($contact,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($contact===false)$e['contact_id']='Select a valid contact.';
  if($e!==[])return[null,$e];$rules=$sales['interaction_outcomes'][$outcome];$qualifies=(bool)$rules['contact']&&$type!=='note'&&$direction!=='internal';
  return[new self($contact?:null,$type,$direction,$date->setTimezone(new DateTimeZone('UTC')),$summary,$outcome,$qualifies,(bool)$rules['response']&&$direction==='inbound'),[]];
 }
}
