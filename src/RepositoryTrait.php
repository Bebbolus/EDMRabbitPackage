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
        $attributes['EDM_DELETED'] = false;
        $attributes['updated_at'] = time();
        if(!isset($attributes['created_at'])) $attributes['created_at'] = time();
        else $attributes['created_at'] = strtotime($attributes['created_at']);

        $message = $this->makeMessage('create',$attributes);

        try{
            $newId = $message->sendWithResponse();
        }catch (\Exception $e){
            throw new EntityNotCreatedException($e->getMessage());
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
            throw new EntityNotUpdatedException($e->getMessage());
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

    public function softDelete()
    {
        $this->setAttribute('EDM_DELETED', false);
        $this->save();
    }

    public function delete()
    {
        if(!isset($attributes['ID'])) $attributes['ID'] = $this->getElsId();
        $message = $this->makeMessage('delete',$attributes);

        try{
            return $message->sendWithResponse();
        }catch (\Exception $e){
            throw new EntityNotDeletedException($e->getMessage());
        }
    }



}