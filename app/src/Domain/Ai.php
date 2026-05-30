<?php

namespace Domain;

/*
 *  This class represent the IA and how  
 *  data are processing 
 */

use Domain\LlmAdaptaterInterface;

class Ai {


    private string $name;                   // name of the model
    private string $infoContextWindow;      // size of the context window of the model  
    private string $infoSizeOfModel;        // size of the model
    private string $infoCompagny;           // compagny who delivery the model
    private string $url;                    // address of the api
    private LlmAdaptaterInterface $adaptater;   // type of adaptator

    public function __construct(string $name, string $infoContextWindow ,string $infoSizeOfModel, string $infoCompagny, string $url, LlmAdaptaterInterface $adaptater) {
        $this->name = $name;
        $this->infoContextWindow = $infoContextWindow;
        $this->infoSizeOfModel = $infoSizeOfModel;
        $this->infoCompagny = $infoCompagny;
        $this->url = $url;
        $this->adaptater = $adaptater;
    }

    public function ask(string $message, array $context): string {
        return $this->adaptater->generate($message, $context);
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