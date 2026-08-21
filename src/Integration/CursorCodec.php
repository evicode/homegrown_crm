<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
final class CursorCodec{
 public function __construct(private readonly string $key){}
 /** @param array<string,mixed> $payload */ public function encode(array $payload):string{$json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$data=rtrim(strtr(base64_encode($json),'+/','-_'),'=');return $data.'.'.hash_hmac('sha256',$data,$this->key);}
 /** @return array<string,mixed> */ public function decode(string $cursor):array{[$data,$signature]=array_pad(explode('.',$cursor,2),2,null);if(!is_string($data)||!is_string($signature)||!hash_equals(hash_hmac('sha256',$data,$this->key),$signature))throw new \DomainException('Cursor is invalid.');$json=base64_decode(strtr($data,'-_','+/'),true);$decoded=is_string($json)?json_decode($json,true):null;if(!is_array($decoded)||((int)($decoded['expires_at']??0)<time()))throw new \DomainException('Cursor is invalid or expired.');return $decoded;}
}
