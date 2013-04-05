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

##Create the patch file(s)

use example file at Ressources/patch/patches_examples.xml to create your patch files keeping the XML structure of that file.
Sympatch will look for files Ressources/patch/patches_*.xml to load the patches.


##usage:
<pre>
php app/console patch --func=list
php app/console patch --func=listall
php app/console patch --func=update
php app/console patch --func=deactivateall
</pre>


##hints:

- when specifying code locations in <beforecode> or <aftercode> tags, copy complete lines from the source file(s) to the xml file
- when running the tests there should be no patch file in /Resources/patch (files like patches_*.xml)
- the use of <beforecode>, <aftercode> is encouraged over <beforeline>, <afterline> for two reasons:
  more than one patch in the same file will not cause problems of keeping track of line numbers because of line shifts caused by each patch
  better chance to have patches working after code base upgrades without modifications
- the deactivateall command has been added to allow code base upgrades without version collisions
  the sequence would be:
    php app/console patch --func=deactivateall
    do upgrades (e.g. php composer.phar update)
    php app/console patch --func=update