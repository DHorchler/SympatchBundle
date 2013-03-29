<?php
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use DHorchler\SympatchBundle\Command\PatchCommand;
use DHorchler\SympatchBundle\Helpers\ExSimpleXMLElement;

class ListCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $patchesArray;    
    
    public function testExecute()
    {
        $application = new Application();
        $application->add(new PatchCommand());
        $command = $application->find('patch');
        $commandTester = new CommandTester($command);
        $patchFile =  __DIR__.'/../TestObjects/patches.xml';
        $this->createPatchFile($patchFile);
        $this->doSteps($commandTester, $command, $patchFile);
    }

    protected function doSteps($commandTester, $command, $patchFile)
    {
        $commandTester->execute(array('command' => $command->getName(), 'patchfile' => $patchFile, '--func' => 'listall'));
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
                 $this->assertNotContains('//start patch '.$patchArray['name'], $source, true);
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
        
        $php1 = 'echo patch001 executing now;
                   echo patch001 still executing;';
        $php2 = 'echo patch002 executing now;
                   echo patch002 executing now;';
        $php3 = 'echo patch003 executing now;
                   echo patch003 executing now;';
        $this->patchesArray = array(
            array('name' => 'patch001', 'title' => 'patch001', 'php' => $php1, 'status' => 'active', 'file' => $patchedFile, 'beforecode' => '', 'aftercode' => $afterCode1, 'beforeline' => '', 'afterline' => ''),
            array('name' => 'patch002', 'title' => 'patch002', 'php' => $php2, 'status' => 'active', 'file' => $patchedFile, 'beforecode' => '', 'afterCode' => '', 'beforeline' => 7, 'afterline' => ''),
            array('name' => 'patch003', 'title' => 'patch003', 'php' => $php3, 'status' => 'inactive', 'file' => $patchedFile, 'beforecode' => '', 'aftercode' => '', 'beforeline' => 12, 'afterline' => ''),
        );       
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