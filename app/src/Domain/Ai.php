<?php

namespace Model;

/*
 *  This class represent the IA and how  
 *  is does the data processing 
 */

class Ai {


    private string $name;                   // name of the model
    private string $url;                    // address of the api
    private LlmAdapterInterface $adapter;   // type of adaptator

    public function __construct(string $name, string $url, LlmAdapterInterface $adapter) {
        $this->name = $name;
        $this->url = $url;
        $this->adapter = $adapter;
    }

    public function ask(string $message, array $context): string {
        return $this->adapter->generate($message, $context);
    }


    // Getters & Setters
    public function getName(){
        return $this->name;
    }

    public function getUrl(){

    }

    public function setName(string $name){

    }

    public function setUrl(string $url){

    }
}