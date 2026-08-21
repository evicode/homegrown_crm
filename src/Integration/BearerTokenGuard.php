<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use Dreamsmith\Campaign\Application\Audit\ActorContext;use Dreamsmith\Campaign\Http\Request;
final class BearerTokenGuard{
 public function __construct(private readonly LocalTokenAuthenticator $tokens){}
 public function authenticate(Request $request):ActorContext{$header=$request->headers['authorization']??'';if(!is_string($header)||preg_match('/^Bearer ([A-Za-z0-9_-]{20,512})$/',$header,$matches)!==1)throw new \DomainException('Authentication required.');return $this->tokens->authenticate($matches[1],$request->requestId);}
}
