<?php

namespace App\Service; 

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SavePictures {
    private $fileName;
    private $filePath;
    private $projectDir;
    
    public function __construct(ParameterBagInterface $params)
    {
        $this->projectDir = $params->get('kernel.project_dir');
    }
    
    public function saveFile(string $url): void
    {
        try {
            // Créer le dossier img s'il n'existe pas
            $imgDir = $this->projectDir . '/public/img';
            if (!file_exists($imgDir)) {
                mkdir($imgDir, 0777, true);
            }

            // Télécharger l'image
            $fileContent = file_get_contents($url);
            if ($fileContent === false) {
                throw new \Exception("Impossible de télécharger l'image depuis l'URL");
            }

            // Générer un nom unique
            $this->fileName = uniqid() . '.webp';
            $this->filePath = 'img/' . $this->fileName;
            $fullPath = $this->projectDir . '/public/' . $this->filePath;

            // Sauvegarder l'image
            if (file_put_contents($fullPath, $fileContent) === false) {
                throw new \Exception("Impossible de sauvegarder l'image");
            }
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la sauvegarde de l'image : " . $e->getMessage());
        }
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getPathName(): string
    {
        return $this->filePath;
    }
}