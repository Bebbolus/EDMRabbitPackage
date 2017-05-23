<?php

namespace EDMRabbitPackage;

use EDMRabbitPackage\Interfaces\MessageInterface;
use ElasticSessions\Exceptions\EntityNotUpdatedException;
use ElasticSessions\Exceptions\EntityNotCreatedException;
use ElasticSessions\Exceptions\EntityNotDeletedException;

trait RepositoryTrait
{
    use \ELSRepositoryTrait\RepositoryTrait;

    private function getUser()
    {
        if(!auth()->check()){
            return 'GUEST';
        }else{
            return auth()->user()->getUserEmail();
        }
    }


    private function makeMessage($action, $attributes){
        return new MessageInterface($this->getIndexName(),$this->getTypeName(),$action,$attributes,$this->getUser());
    }

    public function create($attributes)
    {
        $attributes['updated_at'] = time();
        if(!isset($attributes['created_at'])) $attributes['created_at'] = time();
        else $attributes['created_at'] = strtotime($attributes['created_at']);

        $message = $this->makeMessage('create',$attributes);

        try{
            $newId = $message->sendWithResponse();
        }catch (\Exception $e){
            throw new EntityNotCreatedException();
        }
        return $this->find($newId);
    }

    public function update(array $attributes = [], array $options = [])
    {
        if(!isset($attributes['ID'])) $attributes['ID'] = $this->getElsId();

        $attributes['updated_at'] = time();
        if(!isset($attributes['created_at'])) $attributes['created_at'] = time();
        else $attributes['created_at'] = strtotime($attributes['created_at']);

        $message = $this->makeMessage('update',$attributes);

        try{
            return $message->sendWithResponse();
        }catch (\Exception $e){
            dd($e);
            throw new EntityNotUpdatedException();
        }
    }

    public function save(array $options = [])
    {
        try{
            $id = $this->getElsId();
        }catch(\Exception $e){
            return $this->create($this->attributes);
        }

        return $this->update($this->attributes);

    }

    public function delete()
    {
        $message = $this->makeMessage('delete',$this->attributes);

        try{
            return $message->sendWithResponse();
        }catch (\Exception $e){
            throw new EntityNotDeletedException();
        }
    }

    /*
     * Options can be:
     * 'DOCUMENT_CODE'=> (string)$a->code,
            'DOCUMENT_TITLE'=> (string)$a->code.'.pdf',
                'DOCUMENT_MIME_TYPE' => 'application/pdf',
                'USERNAME'
                'USER_ID'
                'REPOSITORY:
                'PATH': '_SBDS_share',
                'OCR': 0,1
     */
    public function notifyUpload($attributes)
    {
        $message = $this->makeMessage('notify_upload',$this->attributes);

        try{
            return $message->sendWithResponse();
        }catch (\Exception $e){
            dd($e);
        }
    }


    /*
     * action TBD:
     *      - mail
     *      - ocr
     *      - get_versions
     *      - read_versions
     */



}