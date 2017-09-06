<?php
// src/AppBundle/DataFixtures/ORM/LoadUserData.php

namespace Ibnab\Bundle\PmanagerBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\ConfigBundle\Entity\ConfigValue;
use Oro\Bundle\ConfigBundle\Entity\Config;
class LoadConfidData implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        
        $allConfigs  =  array( "textheader" , "logosize" , "titleheader" , "marginheader" , "marginfooter" , "logoupload") ;
        $configObject = $manager->getRepository('Oro\Bundle\ConfigBundle\Entity\Config')->find(1);
       
        $configsValueRemove = $manager->getRepository('Oro\Bundle\ConfigBundle\Entity\ConfigValue')->findBy(array('section' => 'ibnab_pmanager'));
        foreach($configsValueRemove as $configValueRemove){
            $manager->remove($configValueRemove);
        }
        $manager->flush();
        foreach($allConfigs as $config){
           $configValue= new ConfigValue();
           $configValue->setConfig($configObject);
           $configValue->setName($config); 
           $configValue->setSection('ibnab_pmanager');
           $manager->persist($configValue);
        }
        $manager->flush();
    }
}