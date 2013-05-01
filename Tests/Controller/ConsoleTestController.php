<?php
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use DHorchler\SympatchBundle\Command\PatchCommand;
use DHorchler\SympatchBundle\Helpers\ExSimpleXMLElement;
use Symfony\Component\Finder\Finder;

class PatchCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $patchesArray;

    //this test creates its own patch file /Resources/patch/patches_phpunittests.xml. Files /Resources/patch/patches_*.xml will be renamed to /Resources/patch/patches_*.xml.unittest at testing time.
    public function testExecute()
    {
        $patchFileFinder = new Finder();
        $patchFileFinder->files()->in( __DIR__.'/../../Resources/patch/')->name('patches_*.xml');
        $renamedFiles = array();
        foreach ($patchFileFinder AS $patchFile) {rename($patchFile, $patchFile.'.unittest'); $renamedFiles[] = $patchFile;}
        $application = new Application();
        $application->add(new PatchCommand());
        $command = $application->find('patch');
        $commandTester = new CommandTester($command);
        $patchFile =  __DIR__.'/../../Resources/patch/patches_phpunittests.xml';
        echo PHP_EOL.'creating patch file '.$patchFile.PHP_EOL;
        $this->createPatchFile($patchFile);
        $this->doSteps($commandTester, $command, $patchFile);
        echo PHP_EOL.'deactivating all test patches'.PHP_EOL;
        foreach ($this->patchesArray AS &$patchArray) $patchArray['status'] = 'inactive';
        $this->savePatchFile($patchFile);
        $commandTester->execute(array('command' => $command->getName(), '--func' => 'update'));
        foreach ($this->patchesArray AS $patchArray)
        {
            $source = file_get_contents( __DIR__.'/../../../../../../../'.$patchArray['file']);
            $this->assertNotContains('//start patch '.$patchArray['name'], $source, 'patch '.$patchArray['name'].' could not be removed');
        }
        echo PHP_EOL.'deleting patch test file'.PHP_EOL;
        unlink($patchFile);
        echo PHP_EOL.'renaming existing patch files'.PHP_EOL;
        foreach ($renamedFiles AS $renamedFile) rename($renamedFile.'.unittest', $renamedFile);
    }

    protected function doSteps($commandTester, $command, $patchFile)
    {
        $commandTester->execute(array('command' => $command->getName(), '--func' => 'listall'));
        $display =  $commandTester->getDisplay();
        foreach ($this->patchesArray AS $patchArray)
        {
            $this->assertRegExp('%'.$patchArray['name'].' \('.$patchArray['status'].'\)%', $display);
            $this->assertRegExp('%title: '.$patchArray['name'].'%', $display);
            $this->assertRegExp('%file: '.$patchArray['file'].'%', $display);
            if (isset ($patchArray['beforeline']) AND $patchArray['beforeline'] != '') $this->assertRegExp('%before line: '.$patchArray['beforeline'].'%', $display);
            if (isset($patchArray['afterline']) AND $patchArray['afterline'] != '') $this->assertRegExp('%after line: '.$patchArray['afterline'].'%', $display);
            if (isset($patchArray['beforecode']) AND $patchArray['beforecode'] != '') $this->assertRegExp('%before code: %', $display);
            if (isset($patchArray['aftercode']) AND $patchArray['aftercode'] != '') $this->assertRegExp('%after code: %', $display);
        }
        $commandTester->execute(array('command' => $command->getName(), 'patchfile' => $patchFile, '--func' => 'list'));
        $display =  $commandTester->getDisplay();
        foreach ($this->patchesArray AS $patchArray)
        {
            $this->assertRegExp('%'.$patchArray['name'].' \('.$patchArray['status'].'\)%', $display);
        }
        $commandTester->execute(array('command' => $command->getName(), 'patchfile' => $patchFile));
        $display =  $commandTester->getDisplay();
        foreach ($this->patchesArray AS $patchArray)
        {
            $this->assertRegExp('%'.$patchArray['name'].' \('.$patchArray['status'].'\)%', $display);
        }
        $commandTester->execute(array('command' => $command->getName(), 'patchfile' => $patchFile, '--func' => 'update'));
        $display =  $commandTester->getDisplay();
        foreach ($this->patchesArray AS $patchArray)
        {
            $this->assertRegExp('%'.$patchArray['name'].' \('.$patchArray['status'].'\) has been updated%', $display);
            $source = file_get_contents( __DIR__.'/../../../../../../../'.$patchArray['file']);
            if ($patchArray['status'] == 'active')
            {
                $this->assertContains('//start patch '.$patchArray['name'], $source);
                $this->assertContains('//end patch', $source);
            }
            else
            {
                 $this->assertNotContains('//start patch '.$patchArray['name'], $source);
            }
        }
    }
    
    protected function createPatchFile($patchFile)
    {
        $patchedFile = 'vendor/dhorchler/sympatch-bundle/DHorchler/SympatchBundle/Tests/TestObjects/PatchCommand.txt';
        $afterCode1 = PHP_EOL;//necessary fillpattern, will be stripped
        $afterCode1 .= <<<'EOT'
            ->addOption('func', 'f', InputOption::VALUE_OPTIONAL)
            //->addArgument('all', InputArgument::OPTIONAL, '')
        ;
EOT;
        $afterCode1 .= PHP_EOL;//necessary fillpattern, will be stripped
        
        $insertcode1 = 'echo phpunittest_patch001 executing now;
                   echo phpunittest_patch001 still executing;';
        $insertcode2 = 'echo phpunittest_patch002 executing now;
                   echo phpunittest_patch002 executing now;';
        $insertcode3 = 'echo phpunittest_patch003 executing now;
                   echo phpunittest_patch003 executing now;';
        $this->patchesArray = array(
            array('name' => 'phpunittest_patch001', 'title' => 'phpunittest_patch001', 'comment' => 'comment1', 'status' => 'active', 'codetype' => 'php', 'file' => $patchedFile, 'aftercode' => $afterCode1, 'insertcode' => $insertcode1),
            array('name' => 'phpunittest_patch002', 'title' => 'phpunittest_patch002', 'comment' => 'comment1', 'status' => 'active', 'codetype' => 'php', 'file' => $patchedFile, 'beforeline' => 7, 'insertcode' => $insertcode2),
            array('name' => 'phpunittest_patch003', 'title' => 'phpunittest_patch003', 'comment' => 'comment1', 'status' => 'inactive', 'codetype' => 'php', 'file' => $patchedFile, 'beforeline' => 12, 'insertcode' => $insertcode3),
        );       
        $this->savePatchFile($patchFile);
    }

    protected function savePatchFile($patchFile)
    {
        $patchesXML = new ExSimpleXMLElement("<patches><processed>yes</processed></patches>");
        foreach ($this->patchesArray AS $patchArray)
        {
            $patchXML = $patchesXML->addChild('patch');
            $patchXML->addAttribute('name', $patchArray['name']);
            foreach ($patchArray AS $key => $value) if ($key != 'name' AND $value != '') $patchXML->addChildCData($key, $value);
            file_put_contents($patchFile, $patchesXML->asXML());
        }
    }
}