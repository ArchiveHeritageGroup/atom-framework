#!/usr/bin/env node
//
// Recent inbound mail, for the deploy gate.
//
// Lists what has arrived in Johan's mailbox in the last N hours and marks the
// ones that read like fault reports, so a deploy does not go out on top of an
// unread "X is broken" sitting in the inbox. On 2026-08-24 four such reports
// arrived over two hours while unrelated work was being released.
//
// Reads through the Workbench's own Graph client rather than a hand-rolled
// Graph or IMAP call - that client owns the OAuth refresh and the encrypted
// token, and duplicating it would mean duplicating the key handling.
//
// Usage: mail-check.cjs [--hours N] [--json]
//
// Exit 0 always. This reports; it never decides. Whether a waiting report
// should stop a deploy is a judgement for the person deploying, and a mail
// server having a bad afternoon must not be able to block a release.

const path = require('path');

const API = process.env.WORKBENCH_API_DIR || '/usr/share/nginx/workbench/api';
const UPN = process.env.MAIL_CHECK_UPN || 'johan@theahg.co.za';

const args = process.argv.slice(2);
const asJson = args.includes('--json');
const hoursArg = args.indexOf('--hours');
const HOURS = hoursArg !== -1 ? parseFloat(args[hoursArg + 1]) || 24 : 24;

// Subjects that mean somebody is telling us something is wrong. Matched on the
// subject only - bodies are not read here, and are not this script's business.
const FAULT_WORDS = /\b(error|errors|bug|fail|failed|failure|broken|crash|500|404|not\s+found|cannot|can't|unable|issue|problem|down|missing)\b/i;

function skip(reason) {
    if (asJson) {
        console.log(JSON.stringify({ status: 'skipped', reason, messages: [] }));
    } else {
        console.log(`  SKIP - ${reason}`);
    }
    process.exit(0);
}

(async () => {
    const fs = require('fs');
    if (!fs.existsSync(path.join(API, 'dist/services/outlookGraph.js'))) {
        skip(`no Workbench Graph client at ${API}`);
    }

    require(path.join(API, 'node_modules', 'dotenv')).config({ path: path.join(API, '.env') });

    const { pool } = require(path.join(API, 'dist/lib/db.js'));
    const repoMod = require(path.join(API, 'dist/repositories/outlookAccounts.js'));
    const graph = require(path.join(API, 'dist/services/outlookGraph.js'));
    const repo = repoMod.outlookAccountsRepo || repoMod.outlookAccounts || repoMod.default || repoMod;

    // Newest oauth account for the mailbox that still holds a refresh token -
    // the same row outboundMail.ts sends through.
    const found = await pool.query(
        `SELECT id FROM outlook_accounts
          WHERE kind = 'oauth' AND lower(upn) = lower($1) AND enc_refresh_token IS NOT NULL
          ORDER BY updated_at DESC LIMIT 1`,
        [UPN],
    );
    const accountId = found.rows[0] && found.rows[0].id;
    if (!accountId) {
        skip(`no authenticated mailbox for ${UPN}`);
    }

    const account = await repo.findById(accountId);
    if (!account) {
        skip(`mailbox ${UPN} is not readable`);
    }

    const since = Date.now() - HOURS * 3600 * 1000;
    const messages = await graph.listMessages({ account, folder: 'inbox', top: 50 });

    // Mail from the mailbox owner is not an inbound report. Outlook drops a
    // copy of a reply into the inbox thread, so without this the reply you just
    // sent about a bug comes back flagged as a fresh bug report.
    const self = UPN.toLowerCase();

    const recent = messages
        .filter((m) => m.receivedDateTime && Date.parse(m.receivedDateTime) >= since)
        .filter((m) => !(m.from || '').toLowerCase().includes(self))
        .map((m) => ({
            received: m.receivedDateTime,
            from: m.from || 'unknown sender',
            subject: m.subject || '(no subject)',
            looksLikeFault: FAULT_WORDS.test(m.subject || ''),
        }));

    if (asJson) {
        console.log(JSON.stringify({ status: 'ok', hours: HOURS, messages: recent }));
        process.exit(0);
    }

    const faults = recent.filter((m) => m.looksLikeFault);

    if (recent.length === 0) {
        console.log(`  nothing in ${UPN} in the last ${HOURS}h`);
        process.exit(0);
    }

    console.log(`  ${recent.length} message(s) in the last ${HOURS}h, ${faults.length} reading like a fault report`);

    for (const m of recent) {
        const age = Math.round((Date.now() - Date.parse(m.received)) / 60000);
        const flag = m.looksLikeFault ? '  >> ' : '     ';
        console.log(`${flag}${String(age).padStart(4)}m ago  ${m.from}`);
        console.log(`          ${m.subject}`);
    }

    if (faults.length > 0) {
        console.log('');
        console.log('  Read the ones marked >> before deploying. They may describe what you are about to ship over.');
    }

    process.exit(0);
})().catch((e) => {
    // A mail outage is not a deploy failure.
    skip(`mail check could not run: ${String(e && e.message).slice(0, 160)}`);
});
