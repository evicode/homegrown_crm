<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Reporting;
final class ListQuery{
 public function __construct(public readonly string $text,public readonly int $page,public readonly int $size=25){}
 public static function from(array $query):self{$text=trim((string)($query['q']??''));return new self(substr($text,0,120),max(1,(int)($query['page']??1)));}
 public function offset():int{return($this->page-1)*$this->size;}
}
