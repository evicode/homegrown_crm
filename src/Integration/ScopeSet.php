<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
final class ScopeSet{
 /** @param list<string> $scopes */ public function __construct(private readonly array $scopes){}
 /** @return list<string> */ public function all():array{return $this->scopes;}
 public function allows(string $scope):bool{return in_array($scope,$this->scopes,true);}
 /** @param list<string> $required */ public function allowsAll(array $required):bool{foreach($required as $scope)if(!$this->allows($scope))return false;return true;}
 /** @param list<string>|string $value @param array<string,string> $allowed */ public static function validated(array|string $value,array $allowed):self{$values=is_string($value)?preg_split('/\s+/',trim($value)):$value;$values=array_values(array_unique(array_filter($values,static fn($scope):bool=>is_string($scope)&&isset($allowed[$scope]))));sort($values,SORT_STRING);return new self($values);}
}
