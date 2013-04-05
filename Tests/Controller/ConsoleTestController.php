<?php
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use DHorchler\SympatchBundle\Command\PatchCommand;
use DHorchler\SympatchBundle\Helpers\ExSimpleXMLElement;
use Symfony\Component\Finder\Finder;

class ListCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $patchesArray;    
    
    public function testExecute()
    {//this test creates its own patch file /Resources/patch/patches_phpunittests.xml. There should be no other file /Resources/patch/patches_*phpunittests*.xml at testing time.
        $finder = new Finder();
        $finder->files()->in( __DIR__.'/../../Resources/patch/')->name('patches_*.xml');
        if (count($finder) > 0) die(PHP_EOL.'please remove or rename files /Resources/patch/patches_*.xml before starting the tests');
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
        unlink($patchFile);
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
        
        $code1 = 'echo phpunittest_patch001 executing now;
                   echo phpunittest_patch001 still executing;';
        $code2 = 'echo phpunittest_patch002 executing now;
                   echo phpunittest_patch002 executing now;';
        $code3 = 'echo phpunittest_patch003 executing now;
                   echo phpunittest_patch003 executing now;';
        $this->patchesArray = array(
            array('name' => 'phpunittest_patch001', 'title' => 'phpunittest_patch001', 'comment' => 'comment1', 'status' => 'active', 'codetype' => 'php', 'file' => $patchedFile, 'aftercode' => $afterCode1, 'code' => $code1),
            array('name' => 'phpunittest_patch002', 'title' => 'phpunittest_patch002', 'comment' => 'comment1', 'status' => 'active', 'codetype' => 'php', 'file' => $patchedFile, 'beforeline' => 7, 'code' => $code2),
            array('name' => 'phpunittest_patch003', 'title' => 'phpunittest_patch003', 'comment' => 'comment1', 'status' => 'inactive', 'codetype' => 'php', 'file' => $patchedFile, 'beforeline' => 12, 'code' => $code3),
        );       
        $this->savePatchFile($patchFile);
    }

    protected function savePatchFile($patchFile)
    {
        $patchesXML = new ExSimpleXMLElement("<patches></patches>");
        foreach ($this->patchesArray AS $patchArray)
        {
            $patchXML = $patchesXML->addChild('patch');
            $patchXML->addAttribute('name', $patchArray['name']);
            foreach ($patchArray AS $key => $value)
            {
                if ($key != 'name' AND $value != '') $patchXML->addChildCData($key, $value); 
            }
            file_put_contents($patchFile, $patchesXML->asXML());
        }
    }
}