<?php
namespace Codeception\Module;

class GimmeHelper extends \Codeception\Module
{

    /**
     * Set required config parameters.
     *
     * All parameters available in $this->config['parameter']
     */
    protected $requiredFields = array('site_id');

    /**
     * Before each test
     */
    public function _before()
    {
        $this->resetSite();
    }

    /**
     * Enables us to clean the database in an abstract way for integration
     * via the configuration files. Namely our CI service (Travis-CI.com)
     *
     * @throws \Codeception\Exception\Module
     */
    protected function resetSite()
    {

 		file_get_contents( "http://gimme.subscription.beer/?action=reset&key=DAzaEguw8j8BDs&id=" . $this->config['site_id'] );

    }

}
