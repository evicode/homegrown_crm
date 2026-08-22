<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Presentation\FlashBag;
use Dreamsmith\Campaign\Presentation\ShellRenderer;
use Dreamsmith\Campaign\Security\Csrf;

final class RecordController
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly Database $database,
        private readonly RecordService $service,
        private readonly ShellRenderer $shell,
        private readonly FlashBag $flash,
        private readonly Csrf $csrf,
        private readonly Router $router,
    ) {
    }

    public function companies(Request $request): Response
    {
        $user = $this->user(); if ($user instanceof Response) return $user;
        $archived = ($request->query['archived'] ?? null) === '1';
        $list = \Dreamsmith\Campaign\Reporting\ListQuery::from($request->query, ['name', 'updated', 'location', 'industry'], 'name');
        $repository = new RecordRepository($this->database->pdo());
        $count = $repository->companyCount($archived, $list->text);
        return $this->shell->owner('records/companies.php', [
            'companies' => $repository->companies($archived, $list->text, $list->sort, $list->direction, $list->size, $list->offset()),
            'archived' => $archived,
            'filters' => ['q' => $list->text, 'sort' => $list->sort, 'direction' => $list->direction, 'archived' => $archived ? '1' : ''],
            'count' => $count, 'page' => $list->page, 'pages' => max(1, (int) ceil($count / $list->size)),
            'indexUrl' => $this->router->url('companies.index'),
            'newUrl' => $this->router->url('companies.new'),
            'activeUrl' => $this->router->url('companies.index'),
            'archivedUrl' => $this->router->url('companies.index') . '?archived=1',
            'showUrl' => fn (int $id): string => $this->router->url('companies.show', ['id' => $id]),
            'newContactUrl' => $this->router->url('contacts.new'),
        ], $user, 'Companies', 'companies.index');
    }

    public function company(int $id): Response
    {
        $user = $this->user(); if ($user instanceof Response) return $user;
        $repository = new RecordRepository($this->database->pdo());
        $company = $repository->company($id);
        if ($company === null) return Response::html('Company not found.', 404);
        return $this->shell->owner('records/company.php', [
            'company'=>$company, 'contacts'=>$repository->companyContacts($id), 'csrfToken'=>$this->csrf->token(),
            'editUrl'=>$this->router->url('companies.edit',['id'=>$id]), 'indexUrl'=>$this->router->url('companies.index'),
            'newContactUrl'=>$this->router->url('contacts.new').'?company_id='.$id,
            'contactEditUrl'=>fn(int $contactId):string=>$this->router->url('contacts.edit',['id'=>$contactId]),
            'archiveUrl'=>$this->router->url('companies.archive',['id'=>$id]), 'restoreUrl'=>$this->router->url('companies.restore',['id'=>$id]),
            'contactArchiveUrl'=>fn(int $contactId):string=>$this->router->url('contacts.archive',['id'=>$contactId]),
            'contactRestoreUrl'=>fn(int $contactId):string=>$this->router->url('contacts.restore',['id'=>$contactId]),
        ], $user, (string)$company['name'], 'companies.index');
    }

    public function companyForm(?int $id = null, array $values = [], array $errors = [], array $duplicates = [], int $status = 200): Response
    {
        $user = $this->user(); if ($user instanceof Response) return $user;
        $company = $id === null ? null : (new RecordRepository($this->database->pdo()))->company($id);
        if ($id !== null && $company === null) return Response::html('Company not found.',404);
        $values = $values ?: ($company ?? []);
        return $this->shell->owner('records/company-form.php', [
            'company'=>$company,'values'=>$values,'errors'=>$errors,'duplicates'=>$duplicates,'csrfToken'=>$this->csrf->token(),
            'actionUrl'=>$id===null?$this->router->url('companies.create'):$this->router->url('companies.update',['id'=>$id]),
            'cancelUrl'=>$id===null?$this->router->url('companies.index'):$this->router->url('companies.show',['id'=>$id]),
        ], $user, $id===null?'New company':'Edit company', 'companies.index', $status);
    }

    public function saveCompany(Request $request, ?int $id = null): Response
    {
        $user=$this->user(); if($user instanceof Response)return $user;
        if(!$this->csrf->verify($request->body['_csrf']??null))return Response::html('Forbidden',403);
        [$input,$errors]=CompanyInput::fromArray($request->body);
        if($input===null)return $this->companyForm($id,$request->body,$errors,[],422);
        try {
            if($id===null){$id=$this->service->createCompany($input,($request->body['confirm_duplicate']??null)==='1',(int)$user['id'],$request->requestId);}
            else{$this->service->updateCompany($id,$input,(int)($request->body['version']??0),($request->body['confirm_duplicate']??null)==='1',(int)$user['id'],$request->requestId);}
        } catch(DuplicateWarning $warning){return $this->companyForm($id,$request->body,[], $warning->matches,409);}
          catch(StaleRecordVersion $exception){return $this->companyForm($id,[],['conflict'=>$exception->getMessage()],[],409);}
        $this->flash->add('success',$request->body['version']??null?'Company updated.':'Company created.');
        return Response::redirect($this->router->url('companies.show',['id'=>$id]));
    }

    /** Create a company from another form and return the new selectable record. */
    public function quickCreateCompany(Request $request): Response
    {
        $user = $this->user();
        if ($user instanceof Response) return Response::json(['error' => 'Sign in again before creating a company.'], 401);
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) return Response::json(['error' => 'Your session expired. Refresh this page and try again.'], 403);
        [$input, $errors] = CompanyInput::fromArray($request->body);
        if ($input === null) return Response::json(['error' => reset($errors) ?: 'Enter a valid company.'], 422);
        try {
            $id = $this->service->createCompany($input, false, (int) $user['id'], $request->requestId);
        } catch (DuplicateWarning $warning) {
            return Response::json(['error' => 'A possible duplicate already exists: ' . implode('; ', $warning->matches)], 409);
        }
        return Response::json(['record' => ['id' => $id, 'label' => $input->name]], 201);
    }

    public function toggleCompany(Request $request,int $id,bool $restore):Response
    {
        $user=$this->user();if($user instanceof Response)return $user;
        if(!$this->csrf->verify($request->body['_csrf']??null))return Response::html('Forbidden',403);
        try{
            if($restore)$this->service->restoreCompany($id,(int)($request->body['version']??0),($request->body['confirm_duplicate']??null)==='1',(int)$user['id'],$request->requestId);
            else $this->service->archiveCompany($id,(int)($request->body['version']??0),($request->body['confirm_contacts']??null)==='1',(int)$user['id'],$request->requestId);
            $this->flash->add('success',$restore?'Company restored.':'Company archived.');
        }catch(DuplicateWarning $warning){$this->flash->add('warning','Potential duplicate: '.implode('; ',$warning->matches).'. Confirm restoration to continue.');}
         catch(\DomainException|StaleRecordVersion $exception){$this->flash->add('error',$exception->getMessage());}
        return Response::redirect($this->router->url('companies.show',['id'=>$id]));
    }

    public function contactForm(?int $id=null,array $values=[],array $errors=[],array $duplicates=[],int $status=200,?int $preselectedCompanyId=null):Response
    {
        $user=$this->user();if($user instanceof Response)return $user;
        $repository=new RecordRepository($this->database->pdo());
        $contact=$id===null?null:$repository->contact($id);
        if($id!==null&&$contact===null)return Response::html('Contact not found.',404);
        if($values===[]){$values=$contact??[];if($id===null&&$preselectedCompanyId!==null)$values['company_id']=$preselectedCompanyId;}
        return $this->shell->owner('records/contact-form.php',[
            'contact'=>$contact,'values'=>$values,'errors'=>$errors,'duplicates'=>$duplicates,'companies'=>$repository->activeCompanies(),'csrfToken'=>$this->csrf->token(),
            'actionUrl'=>$id===null?$this->router->url('contacts.create'):$this->router->url('contacts.update',['id'=>$id]),
            'cancelUrl'=>$contact!==null&&$contact['company_id']!==null?$this->router->url('companies.show',['id'=>$contact['company_id']]):$this->router->url('companies.index'),
            'archiveUrl'=>$id===null?null:$this->router->url('contacts.archive',['id'=>$id]),
            'restoreUrl'=>$id===null?null:$this->router->url('contacts.restore',['id'=>$id]),
            'archivedCompanyName'=>$contact!==null&&$contact['company_archived_at']!==null?$contact['company_name']:null,
        ],$user,$id===null?'New contact':'Edit contact','companies.index',$status);
    }

    public function saveContact(Request $request,?int $id=null):Response
    {
        $user=$this->user();if($user instanceof Response)return $user;
        if(!$this->csrf->verify($request->body['_csrf']??null))return Response::html('Forbidden',403);
        [$input,$errors]=ContactInput::fromArray($request->body);
        if($input===null)return $this->contactForm($id,$request->body,$errors,[],422);
        try{
            if($id===null)$id=$this->service->createContact($input,($request->body['confirm_duplicate']??null)==='1',(int)$user['id'],$request->requestId);
            else $this->service->updateContact($id,$input,(int)($request->body['version']??0),($request->body['confirm_duplicate']??null)==='1',($request->body['confirm_archived_reassociation']??null)==='1',(int)$user['id'],$request->requestId);
        }catch(DuplicateWarning $warning){return $this->contactForm($id,$request->body,[],$warning->matches,409);}
         catch(StaleRecordVersion $exception){return $this->contactForm($id,[],['conflict'=>$exception->getMessage()],[],409);}
         catch(\DomainException $exception){return $this->contactForm($id,$request->body,['company_id'=>$exception->getMessage()],[],422);}
        $this->flash->add('success',$request->body['version']??null?'Contact updated.':'Contact created.');
        return Response::redirect($this->router->url('contacts.edit',['id'=>$id]));
    }

    /** Create a contact from another form and return the new selectable record. */
    public function quickCreateContact(Request $request): Response
    {
        $user = $this->user();
        if ($user instanceof Response) return Response::json(['error' => 'Sign in again before creating a contact.'], 401);
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) return Response::json(['error' => 'Your session expired. Refresh this page and try again.'], 403);
        [$input, $errors] = ContactInput::fromArray($request->body);
        if ($input === null) return Response::json(['error' => reset($errors) ?: 'Enter a valid contact.'], 422);
        try {
            $id = $this->service->createContact($input, false, (int) $user['id'], $request->requestId);
        } catch (DuplicateWarning $warning) {
            return Response::json(['error' => 'A possible duplicate already exists: ' . implode('; ', $warning->matches)], 409);
        } catch (\DomainException $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
        $contact = (new RecordRepository($this->database->pdo()))->contact($id);
        $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
        return Response::json(['record' => [
            'id' => $id,
            'label' => $name . (($contact['company_name'] ?? null) ? ' — ' . $contact['company_name'] : ' — Independent'),
        ]], 201);
    }

    public function toggleContact(Request $request,int $id,bool $restore):Response
    {
        $user=$this->user();if($user instanceof Response)return $user;
        if(!$this->csrf->verify($request->body['_csrf']??null))return Response::html('Forbidden',403);
        try{
            if($restore)$this->service->restoreContact($id,(int)($request->body['version']??0),($request->body['confirm_duplicate']??null)==='1',(int)$user['id'],$request->requestId);
            else $this->service->archiveContact($id,(int)($request->body['version']??0),(int)$user['id'],$request->requestId);
            $this->flash->add('success',$restore?'Contact restored.':'Contact archived.');
        }catch(DuplicateWarning $warning){$this->flash->add('warning','Potential duplicate: '.implode('; ',$warning->matches).'. Confirm restoration to continue.');}
         catch(\DomainException|StaleRecordVersion $exception){$this->flash->add('error',$exception->getMessage());}
        return Response::redirect($this->router->url('contacts.edit',['id'=>$id]));
    }

    /** @return array<string,mixed>|Response */
    private function user():array|Response{return $this->auth->user()??Response::redirect($this->router->url('login.form').'?return=dashboard',302);}
}
