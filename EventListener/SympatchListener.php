<?php

namespace DHorchler\SympatchBundle\EventListener;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Finder\Finder;

class SympatchListener
{

    public function __construct()
    {
        
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            $finder = new Finder();
            $finder->files()->in( __DIR__.'/../Resources/patch/')->name('patches_*.xml');
            if (count($finder) <= 0) return;
            foreach ($finder as $patchFile)
            {
                $patches = simplexml_load_file($patchFile);
                $doc = new \DOMDocument;
                //$doc->formatOutput = true;
                $domnode = dom_import_simplexml($patches);
                //$domnode->preserveWhiteSpace = false;
                $domnode = $doc->importNode($domnode, true);
                $domnode = $doc->appendChild($domnode);
                if (!$doc->schemaValidate(__DIR__.'/../Resources/patch/patches.xsd')) die('bad structured XML file');
                if ($patches->processed != 'yes')
                {
                    foreach ($patches->patch AS $patch) \DHorchler\SympatchBundle\Command\PatchCommand::updatePatch($patch, 'update');
                    $patches->processed = 'yes';
                    $patches->asXml($patchFile);
                }
            }
        }
    }
}
