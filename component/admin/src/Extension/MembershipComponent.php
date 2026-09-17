<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use RuntimeException;
use Xdecaro\Component\Decaromembership\Administrator\Service\AnalyticsSourceService;
use Xdecaro\Component\Decaromembership\Administrator\Service\CoreIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\CrossProductIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PeopleIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\ReminderService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PersonMembershipService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MembershipEligibilityService;

final class MembershipComponent extends MVCComponent
{
    private ?CoreIntegrationService $coreIntegration = null;
    private ?CrossProductIntegrationService $crossProduct = null;
    private ?PeopleIntegrationService $peopleIntegration = null;
    private ?AnalyticsSourceService $analytics = null;
    private ?ReminderService $reminders = null;
    private ?PersonMembershipService $personMembership = null;
    private ?MembershipEligibilityService $eligibility = null;

    public function setCoreIntegrationService(CoreIntegrationService $service): void { $this->coreIntegration = $service; }
    public function setCrossProductIntegrationService(CrossProductIntegrationService $service): void { $this->crossProduct = $service; }
    public function setPeopleIntegrationService(PeopleIntegrationService $service): void { $this->peopleIntegration = $service; }
    public function setAnalyticsSourceService(AnalyticsSourceService $service): void { $this->analytics = $service; }
    public function setReminderService(ReminderService $service): void { $this->reminders = $service; }
    public function setPersonMembershipService(PersonMembershipService $service): void { $this->personMembership = $service; }
    public function setMembershipEligibilityService(MembershipEligibilityService $service): void { $this->eligibility = $service; }

    public function getCoreIntegrationService(): CoreIntegrationService
    {
        if ($this->coreIntegration === null) { throw new RuntimeException('Membership Core integration service is unavailable.'); }
        return $this->coreIntegration;
    }

    public function getCrossProductIntegrationService(): CrossProductIntegrationService
    {
        if ($this->crossProduct === null) { throw new RuntimeException('Membership cross-product integration service is unavailable.'); }
        return $this->crossProduct;
    }

    public function getPeopleIntegrationService(): PeopleIntegrationService
    {
        if ($this->peopleIntegration === null) { throw new RuntimeException('Membership People integration service is unavailable.'); }
        return $this->peopleIntegration;
    }

    public function getAnalyticsSourceService(): AnalyticsSourceService
    {
        if ($this->analytics === null) { throw new RuntimeException('Membership Analytics source service is unavailable.'); }
        return $this->analytics;
    }

    public function getReminderService(): ReminderService
    {
        if ($this->reminders === null) { throw new RuntimeException('Membership reminder service is unavailable.'); }
        return $this->reminders;
    }

    public function getPersonMembershipService(): PersonMembershipService
    {
        if ($this->personMembership === null) { throw new RuntimeException('Membership person service is unavailable.'); }
        return $this->personMembership;
    }

    public function getMembershipEligibilityService(): MembershipEligibilityService
    {
        if ($this->eligibility === null) { throw new RuntimeException('Membership eligibility service is unavailable.'); }
        return $this->eligibility;
    }
}
