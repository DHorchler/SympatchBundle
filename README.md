Sympatch
========

Symfony2 code patch tool
#Information

DHorchlerSympatchBundle consists in a tool that enables you to patch code parts anywhere in your Symfony project (including vendors).
It even lets you extend foreign (vendors) entities!

#Features:
- original source file is saved (*.org) when the first patch is entered
- patches can be deactivated without loosing them (for example before composer.phar update)
- code location via line number or before/after code fragments (recommended)



##Get the bundle

Let Composer download and install the bundle by first adding it to your composer.json
<pre>

{
    "require": {
        "dhorchler/sympatch-bundle": "dev-master"
    }
}
</pre>
and then running

<pre>php composer.phar update dhorchler/sympatch-bundle</pre>


##Enable the bundle
in app/AppKernel.php
<pre>
public function registerBundles() {
    $bundles = array(
        // ...
        new DHorchler\SympatchBundle\DHorchlerSympatchBundle(),
    );
    // ...
}
</pre>

##Create the patch file

Just overwrite the example file at Ressources/patch/patches.xml
keeping the XML structure in that file.


##usage:
<pre>
php app/console patch list
php app/console patch listall
php app/console patch update
php app/console patch update {patchfile}
</pre>
