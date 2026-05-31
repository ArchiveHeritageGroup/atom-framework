<?php

/*
 * ahgLdapUser — safe-by-default LDAP user class (#29).
 *
 * Wraps base AtoM ldapUser so LDAP authentication is gated on the runtime
 * `ldap_enabled` setting (Admin > AHG Settings > LDAP authentication). When LDAP
 * is disabled — or the php-ldap extension is absent — it behaves exactly like
 * the standard local-password login (myUser). This makes switching the
 * factories.yml user class to ahgLdapUser non-breaking and reversible.
 *
 * Activation (per server, by an admin):
 *   1. Install the php-ldap extension.
 *   2. In config/factories.yml set:
 *        all:
 *          user:
 *            class: ahgLdapUser
 *            param: { timeout: 1800 }
 *   3. Clear cache + restart php-fpm.
 *   4. Configure + enable LDAP in Admin > AHG Settings > LDAP authentication.
 *
 * Shipped via atom-framework/patches/lib/; bin/install copies it into lib/.
 */
class ahgLdapUser extends ldapUser
{
    /**
     * Only require the php-ldap extension when it is actually present; otherwise
     * fall back to the standard myUser bootstrap so login still works.
     */
    public function initialize(sfEventDispatcher $dispatcher, sfStorage $storage, $options = [])
    {
        if (extension_loaded('ldap')) {
            // ldapUser::initialize (logger + myUser init + ldap extension assertion)
            parent::initialize($dispatcher, $storage, $options);

            return;
        }

        // No php-ldap: skip ldapUser's hard requirement, behave as myUser.
        myUser::initialize($dispatcher, $storage, $options);
    }

    /**
     * Route to LDAP only when explicitly enabled; otherwise local password auth.
     */
    public function authenticate($username, $password)
    {
        if ($this->ldapEnabled()) {
            // ldapUser::authenticate — LDAP bind with local-password fallback.
            return parent::authenticate($username, $password);
        }

        // LDAP disabled: standard local authentication (skips the LDAP path).
        return myUser::authenticate($username, $password);
    }

    /**
     * LDAP is active only when the php-ldap extension is loaded AND an admin has
     * turned it on via the ldap_enabled setting.
     */
    protected function ldapEnabled()
    {
        if (!extension_loaded('ldap')) {
            return false;
        }

        try {
            $setting = QubitSetting::getByName('ldap_enabled');
        } catch (Exception $e) {
            return false;
        }

        return $setting && (bool) (int) $setting->getValue(['sourceCulture' => true]);
    }
}
