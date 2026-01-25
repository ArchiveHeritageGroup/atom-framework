# Change Approval Request Template

Use this template when proposing any code changes:

---

## Proposed Change

**File:** `[full/path/to/file.php]`

**Type:** [ ] New File  [ ] Modification  [ ] Deletion

**Locked Check:** 
- [ ] ✅ File is NOT in locked list
- [ ] ⚠️ File IS locked - requires explicit approval

**Plugin Check:**
- [ ] ✅ Not in a locked plugin
- [ ] ⚠️ Inside locked plugin - requires explicit approval

**Database Approach:**
- [ ] Laravel Query Builder
- [ ] PDO/Propel (for atom_plugin tables only)
- [ ] N/A (no database access)

---

### Summary
[Brief description of what the change does and why]

### Code Preview

```php
// Show the actual code or diff here
```

### Impact
- [ ] No breaking changes
- [ ] Requires database migration
- [ ] Requires cache clear
- [ ] Requires service restart

### Testing Plan
1. Test on 192.168.0.112
2. Verify [specific functionality]
3. Push to 192.168.0.154

---

**May I proceed with this change?**

---

*Wait for explicit "yes" or "approved" before implementing.*
