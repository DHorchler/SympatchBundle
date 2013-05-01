<?php
// src/DHorchler/SympatchBundle/Command/PatchCommands.php
namespace DHorchler\SympatchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;


class PatchCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('patch')
            ->setDescription('Sympatch tool')
            ->addOption('func', 'f', InputOption::VALUE_OPTIONAL)
            ->addArgument('patchfile', InputArgument::OPTIONAL, '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = new Finder();
        $finder->files()->in( __DIR__.'/../Resources/patch/')->name('patches_*.xml');
        if (count($finder) <= 0) die('no patch file found');
        foreach ($finder as $patchFile)
        {
            $output->writeln(PHP_EOL.'<fg=green>opening patch file: '.$patchFile.PHP_EOL.'</fg=green>');
            $options = $input->getOptions();
            $func = (isset($options['func']) AND $options['func'] != '')? $options['func']: 'list';
            $patches = simplexml_load_file($patchFile);
            $dom=new \DOMDocument;
            $dom->load($patchFile);
            if (!$dom->schemaValidate(__DIR__.'/../Resources/patch/patches.xsd')) die('bad structured XML file');
            switch ($func)
            {
                case 'list':
                case 'listall':
                    /*$json_string = json_encode($patches);   
                    print_r(json_decode($json_string, TRUE));*/
                    foreach ($patches->patch AS $patch)
                    {
                         $patch['name'] = trim($patch['name']);
                         $output->writeln('<fg=red>'.$patch['name'].' ('.$patch->status.')'.'</fg=red>');
                         if ($func == 'listall')
                         {
                             $patchArray = explode('<br />', nl2br($patch->insertcode));
                             $output->writeln('<fg=green>title: '.trim($patch->title).'</fg=green>');
                             $output->writeln('<fg=green>file: '.trim($patch->file).'</fg=green>');
                             if (strlen($patch->afterline) != 0) $output->writeln('<fg=green>after line: '.trim($patch->afterline).'</fg=green>');
                             if (strlen($patch->beforeline) != 0) $output->writeln('<fg=green>before line: '.trim($patch->beforeline).'</fg=green>');
                             if (strlen($patch->aftercode) != 0) $output->writeln('<fg=green>after code: '.$patch->aftercode.'</fg=green>');
                             if (strlen($patch->beforecode) != 0) $output->writeln('<fg=green>before code: '.$patch->beforecode.'</fg=green>');
                             foreach ($patchArray AS $patchLine) if (trim($patchLine) != '') $output->writeln('<fg=green>>>'.ltrim($patchLine).'</fg=green>');
                         }
                    }
                    break;
                case 'deactivateall':
                    $fns = array();
                    foreach ($patches->patch AS $patch)
                    {
                         $fns[] = __DIR__.'/../../../../../../'.trim($patch->file);
                    }
                    $fns = array_unique($fns);
                    foreach($fns AS $fn)
                    {
                         if (is_file($fn.'.bak')) unlink($fn.'.bak');
                         if (is_file($fn.'.org'))
                         {
                             unlink($fn);  rename($fn.'.org', $fn);
                         }
                         else
                         {
                            $output->writeln('<fg=red>Caution! File '.$fn.'.org was not found! Manual action required.</red>'); 
                         }
                    }
                    break;
                case 'update':
                    foreach ($patches->patch AS $patch)
                    {
                         $output->writeln('<fg=green>updating patch '.$patch['name'].' ('.$patch->status.')'.'</fg=green>');
                         $this->updatePatch($patch, $func);
                         $output->writeln('<fg=green>patch '.$patch['name'].' ('.$patch->status.')'.' has been updated</fg=green>');
                    }
                    break;
            }
        }
    }
    
    
    static function updatePatch($patch, $func)
    {
         $fn = __DIR__.'/../../../../../../'.trim($patch->file);
         if (is_writable($fn))
         {
             $source = file_get_contents($fn);
             if (!is_file($fn.'.org') AND !is_file($fn.'.bak')) file_put_contents($fn.'.org', $source);
             file_put_contents($fn.'.bak', $source);
             $lines = explode(PHP_EOL, $source);
             if (count($lines) <= 1) $lines = explode("\n", $source);//maybe the file was saved in different surroundings
             if (count($lines) <= 1) $lines = explode("\r\n", $source);
             if (count($lines) <= 1) $lines = explode("\r", $source);                             
             $searchString = '//start patch '.$patch['name'];
             $startFound = false;
             $endFound = false;
             $lastLines = array();
             foreach ($lines as $ln => $line)
             {
                 if (strpos($line, $searchString) !== false)
                 {
                     if ($startFound) {$endFound = true; $endLine = $ln; break;} else {$startFound = true; $startLine = $ln; $searchString = '//end patch';}
                 }
             }
             if ($func == 'deactivateall' OR $patch->status != 'active')
             {
                 $newSource = ($startFound AND $endFound)? implode (PHP_EOL, array_slice($lines, 0, $startLine)).PHP_EOL.implode(PHP_EOL, array_slice($lines, $endLine+1)): $source;
             }
             else
             {
                 $patchArray = explode('<br />', nl2br($patch->insertcode));
                 $formattedPatchArray = array();
                 foreach ($patchArray AS $patchLine) if(trim($patchLine) != '') $formattedPatchArray[] = PHP_EOL.trim($patchLine);
                 $insertArray = array_merge(array(PHP_EOL.'//start patch '.$patch['name']), $formattedPatchArray, array(PHP_EOL.'//end patch'.PHP_EOL));
                 $opciones = array('options' => array('default' => -1, 'min_range' => 0));
                 $afterLine = filter_var(trim($patch->afterline), FILTER_VALIDATE_INT, $opciones);
                 $beforeLine = filter_var(trim($patch->beforeline), FILTER_VALIDATE_INT, $opciones);
                 $afterCode = $patch->aftercode;
                 $beforeCode = $patch->beforecode;
                 if (($afterLine > 0 AND $beforeLine > 0) OR ($afterCode != '' AND $beforeCode != '')) {$output->writeln('<fg=red>Aborted: cannot determine patch location. Possible tags: <afterline>, <beforeline>, <aftercode>, <beforecode>.</fg=red>');exit();}
                 if ($startFound AND $endFound) array_splice($lines, $startLine, $endLine-$startLine+1);                                 
                 if ($afterLine >= 0 AND $afterLine <= count($lines)) $newSource = implode (PHP_EOL, array_slice($lines, 0, $afterLine)).implode('', $insertArray).implode(PHP_EOL, array_slice($lines, $afterLine));
                 if ($beforeLine >= 0 AND $beforeLine <= count($lines)) $newSource = implode (PHP_EOL, array_slice($lines, 0, $beforeLine-1)).implode('', $insertArray).implode(PHP_EOL, array_slice($lines, $beforeLine-1));
                 if (trim($beforeCode) != '' OR trim($afterCode) != '')
                 {
                     $beforeCode = str_replace('<br />', '<BR />', $beforeCode);//mask '<br />' string
                     $afterCode = str_replace('<br />', '<BR />', $afterCode);
                     $codeArray = (trim($beforeCode) != '')? explode('<br />', nl2br($beforeCode)): explode('<br />', nl2br($afterCode));
                     array_shift($codeArray);//strip first and last empty line
                     array_pop($codeArray);
                     foreach ($codeArray AS &$codeLine) $codeLine = ltrim(str_replace('<BR />', '<br />', $codeLine), PHP_EOL);
                     $startLine = 0;
                     $found = false;
                     foreach ($lines as $ln => $line)
                     {
                         for ($l = 0; $l < count($codeArray); $l++)
                         {
                             $found = true;
                             if (($codeArray[$l] == '' AND $lines[$ln+$l] != '') OR ($codeArray[$l] != '' AND strpos($lines[$ln+$l], $codeArray[$l]) !== 0)) {$found = false; break 1;}
                         }
                         if ($found) {$startLine = $ln; break;}
                     }
                     if (!$found) die('Aborted: code location of patch '.$patch['name'].' not found');
                     else
                     {
                         if (trim($afterCode) != '') $startLine += count($codeArray);
                         $newSource = implode (PHP_EOL, array_slice($lines, 0, $startLine)).implode('', $insertArray).implode(PHP_EOL, array_slice($lines, $startLine));
                     }
                 }
             }
             if (!isset($newSource)) die('something went wrong processing patch '.$patch['name'].'. Check if line numbers are within range.');
             file_put_contents($fn, $newSource);
         }
         else
         {
             echo PHP_EOL.'Aborted: file '.$fn.' is not writable!';exit();
         }
    }
}