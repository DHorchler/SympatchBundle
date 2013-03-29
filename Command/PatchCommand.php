<?php
// src/DHorchler/SympatchBundle/Command/PatchCommands.php
namespace DHorchler\SympatchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


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
        $patchFile = (trim($input->getArgument('patchfile')) != '')? $input->getArgument('patchfile'): __DIR__.'/../Resources/patch/patches.xml';
        $options = $input->getOptions();
        $func = (isset($options['func']) AND $options['func'] != '')? $options['func']: 'list';
        $patches = new \SimpleXMLElement(file_get_contents($patchFile));
        //$patches = simplexml_load_file($patchFile, 'SimpleXMLElement', LIBXML_NOCDATA);
        switch ($func)
        {
            case 'list':
            case 'listall':
                /*$json_string = json_encode($patches);   
                print_r(json_decode($json_string, TRUE));*/
                foreach ($patches->patch AS $patch)
                {
                     $output->writeln('<fg=red>'.trim($patch['name']).' ('.$patch->status.')'.'</fg=red>');
                     if ($func == 'listall')
                     {
                         $patchArray = explode('<br />', nl2br($patch->php));
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
            case 'update':
                foreach ($patches->patch AS $patch)
                {
                     $output->writeln('<fg=green>updating patch '.$patch['name'].' ('.$patch->status.')'.'</fg=green>');
                     $fn = __DIR__.'/../../../../../../'.trim($patch->file);
                     if (is_writable($fn))
                     {
                         $source = file_get_contents($fn);
                         if (!is_file($fn.'.org') AND !is_file($fn.'.bak')) file_put_contents($fn.'.org', $source);
                         file_put_contents($fn.'.bak', $source);
                         $lines = explode(PHP_EOL, $source);
                         $searchString = '//start patch '.trim($patch['name']);
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
                         if ($patch->status != 'active')
                         {
                             $newSource = ($startFound AND $endFound)? implode (PHP_EOL, array_slice($lines, 0, $startLine)).implode(PHP_EOL, array_slice($lines, $startLine)): $source;
                         }
                         else
                         {
                             $patchArray = explode('<br />', nl2br($patch->php));
                             $formattedPatchArray = array();
                             foreach ($patchArray AS $patchLine) if(trim($patchLine) != '') $formattedPatchArray[] = PHP_EOL.trim($patchLine);
                             $insertArray = array_merge(array(PHP_EOL.'//start patch '.trim($patch['name'])), $formattedPatchArray, array(PHP_EOL.'//end patch'.PHP_EOL));
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
                                 $codeArray = (trim($beforeCode) != '')? explode('<br />', nl2br($beforeCode)): explode('<br />', nl2br($afterCode));
                                 array_shift($codeArray);//strip first and last empty line
                                 array_pop($codeArray);
                                 foreach ($codeArray AS &$ca) $ca = ltrim($ca);
                                 /*foreach ($codeArray AS &$b) $b = substr($b, 1);
                                 $codeArray[0] = substr($codeArray[0], 12);
                                 $codeArray[count($codeArray)-1] = substr($codeArray[count($codeArray)-1], 0, -2);*/
                                 $startLine = 0;
                                 $found = false;
                                 foreach ($lines as $ln => $line)
                                 {
                                     for ($l = 0; $l < count($codeArray); $l++)
                                     {//echo $l;if ($lines[$ln+$l] == '    public function contactAction()' OR $lines[$ln+$l] == '    {jj') echo PHP_EOL.$lines[$ln+$l].'xxx'.PHP_EOL.$codeArray[$l];
                                         $found = true;
                                         if (($codeArray[$l] == '' AND $lines[$ln+$l] != '') OR ($codeArray[$l] != '' AND strpos(ltrim($lines[$ln+$l]), $codeArray[$l]) !== 0)) {$found = false; break 1;}
                                     }
                                     if ($found) {$startLine = $ln; break;}
                                 }
                                 if (!$found) {echo '<fg=red>Aborted: code location not found</fg=red>';exit();}
                                 else
                                 { 
                                     if (trim($afterCode) != '') $startLine += count($codeArray);
                                     $newSource = implode (PHP_EOL, array_slice($lines, 0, $startLine)).implode('', $insertArray).implode(PHP_EOL, array_slice($lines, $startLine));
                                 }
                             }
                         }
                         file_put_contents($fn, $newSource);
                     }
                     else
                     {
                         echo PHP_EOL.'Aborted: file '.$fn.' is not writable!';exit();
                     }
                     $output->writeln('<fg=green>patch '.$patch['name'].' ('.$patch->status.')'.' has been updated</fg=green>');
                }
                break;
        }
    }
}