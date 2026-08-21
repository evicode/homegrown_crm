<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use Dreamsmith\Campaign\Application\Audit\ActorContext;
final class CapabilityCatalog{
 public function __construct(private readonly array $definitions){}
 /** @return array<string,mixed> */ public function get(string $key):array{if(!isset($this->definitions[$key]))throw new \OutOfBoundsException('Unknown capability.');return $this->definitions[$key];}
 /** @return list<string> */ public function discover(ActorContext $actor):array{$keys=[];foreach($this->definitions as $key=>$definition)if((new ScopeSet($actor->effectiveScopes))->allowsAll($definition['scopes']))$keys[]=$key;sort($keys,SORT_STRING);return $keys;}
 public function authorize(ActorContext $actor,string $key):void{$definition=$this->get($key);if($actor->type!=='integration')throw new \DomainException('External authentication is required.');if(!(new ScopeSet($actor->effectiveScopes))->allowsAll($definition['scopes']))throw new \DomainException('Insufficient scope.');}
 public function requiresIdempotency(string $key):bool{return(bool)$this->get($key)['mutation'];}
}
