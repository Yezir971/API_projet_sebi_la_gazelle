<?php

namespace App\Service; 

class SavePictures{
    public $fileName;
    public $filePath;
    
    public function saveFile(string $url){
        // 2. Télécharger l'image à partir de l'URL fournie par openapi
        $fileUrl = file_get_contents($url);
        $fileName = uniqid() . '.png';
        // On sauvegarde le fileName 
        $this->fileName = $fileName;

        $filePath = '../public/img/' . $fileName;

        // On sauvegarde le filePath 
        $this->filePath = $filePath;
        file_put_contents($filePath, $fileUrl);
        
    }
    public function getFileName(){
        return $this->fileName;
    }
    public function getPathName(){
        return $this->filePath;
    }
}