<?php

/*
 * This class represent the data about LLM in 
 * the DB
 * Its unique role is to interact with the Database 
*/

class AiRepository{
    private string $name;                   // name of the model
    private string $infoContextWindow;      // size of the context window of the model  
    private string $infoSizeOfModel;        // size of the model
    private string $infoCompagny;           // compagny who delivery the model

    public function __construct(string $name, string $infoContextWindow ,string $infoSizeOfModel, string $infoCompagny, string $url, LlmAdapterInterface $adapter) {
        $this->name = $name;
        $this->infoContextWindow = $infoContextWindow;
        $this->infoSizeOfModel = $infoSizeOfModel;
        $this->infoCompagny = $infoCompagny;
    }

    // Getters & Setters
    public function getName(){
        return $this->name;
    }

    public function getInfoContextWindow(){

    }

    public function getInfoSizeOfModel(){

    }

    public function getInfoCompagny(){

    }

    public function getUrl(){

    }

    public function getFormatRequest(){

    }


    public function setName(string $name){

    }

    public function setInfoContextWindow(string $infoContextWindow){

    }

    public function setInfoSizeOfModel(string $infoSizeOfModel ){

    }

    public function setInfoCompagny(string $infoCompagny){

    }

    public function setUrl(string $url){

    }

    public function setFormatRequest(string $formatRequest){

    }

}