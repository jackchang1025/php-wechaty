<?php
/**
 * Created by PhpStorm.
 * User: peterzhang
 * Date: 2020/7/10
 * Time: 7:30 PM
 */

namespace IO\Github\Wechaty\Puppet\Schemas;

class WechatyOptions
{
    public string $name = "Wechaty";
    public string $puppet = "\\IO\\Github\\Wechaty\\PuppetService\\PuppetService";
    public PuppetOptions|null $puppetOptions = null;
    public string|null $ioToken = null;
}