<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('View the shop');
$I->amOnPage('/shop');
$I->see('Shop', '.page-title');