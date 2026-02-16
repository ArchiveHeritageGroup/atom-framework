<?php

namespace AtomFramework\Services\Write;

/**
 * Factory for resolving WriteService interfaces to implementations.
 *
 * Phase 5: Always returns Standalone* implementations (Laravel Query Builder).
 * Propel write services have been removed — the web path is 100% Propel-free.
 *
 * Usage:
 *   $settings = WriteServiceFactory::settings();
 *   $settings->save('hits_per_page', 10);
 *
 *   $acl = WriteServiceFactory::acl();
 *   $acl->savePremisRights($rights, $values);
 *
 *   $do = WriteServiceFactory::digitalObject();
 *   $do->updateMetadata($id, ['media_type_id' => 137]);
 *
 *   $term = WriteServiceFactory::term();
 *   $term->createTerm($taxonomyId, 'New Term');
 *
 *   $acc = WriteServiceFactory::accession();
 *   $acc->createAccession(['identifier' => '2024-001']);
 *
 *   $import = WriteServiceFactory::import();
 *   $import->createOrFindActor('John Doe');
 *
 *   $po = WriteServiceFactory::physicalObject();
 *   $po->createPhysicalObject(['name' => 'Box 1']);
 *
 *   $user = WriteServiceFactory::user();
 *   $user->createUser(['email' => 'test@example.com', 'username' => 'test']);
 *
 *   $actor = WriteServiceFactory::actor();
 *   $actor->createActor(['entity_type_id' => 132, 'authorized_form_of_name' => 'John Doe']);
 *
 *   $fb = WriteServiceFactory::feedback();
 *   $fb->createFeedback(['feed_name' => 'Jane', 'remarks' => 'Great archive']);
 *
 *   $rtp = WriteServiceFactory::requestToPublish();
 *   $rtp->createRequest(['rtp_name' => 'Jane', 'rtp_email' => 'jane@example.com']);
 *
 *   $job = WriteServiceFactory::job();
 *   $job->createJob(['name' => 'arMigrationImportJob', 'user_id' => 1, 'status_id' => 195]);
 */
class WriteServiceFactory
{
    private static ?SettingsWriteServiceInterface $settingsInstance = null;
    private static ?AclWriteServiceInterface $aclInstance = null;
    private static ?DigitalObjectWriteServiceInterface $doInstance = null;
    private static ?TermWriteServiceInterface $termInstance = null;
    private static ?AccessionWriteServiceInterface $accessionInstance = null;
    private static ?ImportWriteServiceInterface $importInstance = null;
    private static ?PhysicalObjectWriteServiceInterface $physicalObjectInstance = null;
    private static ?UserWriteServiceInterface $userInstance = null;
    private static ?ActorWriteServiceInterface $actorInstance = null;
    private static ?FeedbackWriteServiceInterface $feedbackInstance = null;
    private static ?RequestToPublishWriteServiceInterface $rtpInstance = null;
    private static ?JobWriteServiceInterface $jobInstance = null;
    private static ?InformationObjectWriteServiceInterface $ioInstance = null;
    private static ?RightsHolderWriteServiceInterface $rightsHolderInstance = null;

    /**
     * Get the Settings write service.
     */
    public static function settings(): SettingsWriteServiceInterface
    {
        if (null === self::$settingsInstance) {
            self::$settingsInstance = new StandaloneSettingsWriteService();
        }

        return self::$settingsInstance;
    }

    /**
     * Get the ACL write service.
     */
    public static function acl(): AclWriteServiceInterface
    {
        if (null === self::$aclInstance) {
            self::$aclInstance = new StandaloneAclWriteService(self::settings());
        }

        return self::$aclInstance;
    }

    /**
     * Get the Digital Object write service.
     */
    public static function digitalObject(): DigitalObjectWriteServiceInterface
    {
        if (null === self::$doInstance) {
            self::$doInstance = new StandaloneDigitalObjectWriteService();
        }

        return self::$doInstance;
    }

    /**
     * Get the Term write service.
     */
    public static function term(): TermWriteServiceInterface
    {
        if (null === self::$termInstance) {
            self::$termInstance = new StandaloneTermWriteService();
        }

        return self::$termInstance;
    }

    /**
     * Get the Accession write service.
     */
    public static function accession(): AccessionWriteServiceInterface
    {
        if (null === self::$accessionInstance) {
            self::$accessionInstance = new StandaloneAccessionWriteService();
        }

        return self::$accessionInstance;
    }

    /**
     * Get the Import write service.
     */
    public static function import(): ImportWriteServiceInterface
    {
        if (null === self::$importInstance) {
            self::$importInstance = new StandaloneImportWriteService();
        }

        return self::$importInstance;
    }

    /**
     * Get the PhysicalObject write service.
     */
    public static function physicalObject(): PhysicalObjectWriteServiceInterface
    {
        if (null === self::$physicalObjectInstance) {
            self::$physicalObjectInstance = new StandalonePhysicalObjectWriteService();
        }

        return self::$physicalObjectInstance;
    }

    /**
     * Get the User write service.
     */
    public static function user(): UserWriteServiceInterface
    {
        if (null === self::$userInstance) {
            self::$userInstance = new StandaloneUserWriteService();
        }

        return self::$userInstance;
    }

    /**
     * Get the Actor write service.
     */
    public static function actor(): ActorWriteServiceInterface
    {
        if (null === self::$actorInstance) {
            self::$actorInstance = new StandaloneActorWriteService();
        }

        return self::$actorInstance;
    }

    /**
     * Get the Feedback write service.
     */
    public static function feedback(): FeedbackWriteServiceInterface
    {
        if (null === self::$feedbackInstance) {
            self::$feedbackInstance = new StandaloneFeedbackWriteService();
        }

        return self::$feedbackInstance;
    }

    /**
     * Get the Request-to-Publish write service.
     */
    public static function requestToPublish(): RequestToPublishWriteServiceInterface
    {
        if (null === self::$rtpInstance) {
            self::$rtpInstance = new StandaloneRequestToPublishWriteService();
        }

        return self::$rtpInstance;
    }

    /**
     * Get the Job write service.
     */
    public static function job(): JobWriteServiceInterface
    {
        if (null === self::$jobInstance) {
            self::$jobInstance = new StandaloneJobWriteService();
        }

        return self::$jobInstance;
    }

    /**
     * Get the InformationObject write service.
     */
    public static function informationObject(): InformationObjectWriteServiceInterface
    {
        if (null === self::$ioInstance) {
            self::$ioInstance = new StandaloneInformationObjectWriteService();
        }

        return self::$ioInstance;
    }

    /**
     * Get the RightsHolder write service.
     */
    public static function rightsHolder(): RightsHolderWriteServiceInterface
    {
        if (null === self::$rightsHolderInstance) {
            self::$rightsHolderInstance = new StandaloneRightsHolderWriteService();
        }

        return self::$rightsHolderInstance;
    }

    // --- Testing / Override ---

    /**
     * Override a service instance (for testing or custom adapters).
     */
    public static function setSettings(SettingsWriteServiceInterface $service): void
    {
        self::$settingsInstance = $service;
    }

    public static function setAcl(AclWriteServiceInterface $service): void
    {
        self::$aclInstance = $service;
    }

    public static function setDigitalObject(DigitalObjectWriteServiceInterface $service): void
    {
        self::$doInstance = $service;
    }

    public static function setTerm(TermWriteServiceInterface $service): void
    {
        self::$termInstance = $service;
    }

    public static function setAccession(AccessionWriteServiceInterface $service): void
    {
        self::$accessionInstance = $service;
    }

    public static function setImport(ImportWriteServiceInterface $service): void
    {
        self::$importInstance = $service;
    }

    public static function setPhysicalObject(PhysicalObjectWriteServiceInterface $service): void
    {
        self::$physicalObjectInstance = $service;
    }

    public static function setUser(UserWriteServiceInterface $service): void
    {
        self::$userInstance = $service;
    }

    public static function setActor(ActorWriteServiceInterface $service): void
    {
        self::$actorInstance = $service;
    }

    public static function setFeedback(FeedbackWriteServiceInterface $service): void
    {
        self::$feedbackInstance = $service;
    }

    public static function setRequestToPublish(RequestToPublishWriteServiceInterface $service): void
    {
        self::$rtpInstance = $service;
    }

    public static function setJob(JobWriteServiceInterface $service): void
    {
        self::$jobInstance = $service;
    }

    public static function setInformationObject(InformationObjectWriteServiceInterface $service): void
    {
        self::$ioInstance = $service;
    }

    public static function setRightsHolder(RightsHolderWriteServiceInterface $service): void
    {
        self::$rightsHolderInstance = $service;
    }

    /**
     * Reset all cached instances.
     */
    public static function reset(): void
    {
        self::$settingsInstance = null;
        self::$aclInstance = null;
        self::$doInstance = null;
        self::$termInstance = null;
        self::$accessionInstance = null;
        self::$importInstance = null;
        self::$physicalObjectInstance = null;
        self::$userInstance = null;
        self::$actorInstance = null;
        self::$feedbackInstance = null;
        self::$rtpInstance = null;
        self::$jobInstance = null;
        self::$ioInstance = null;
        self::$rightsHolderInstance = null;
    }
}
