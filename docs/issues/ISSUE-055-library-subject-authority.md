# Library AI Enhancement - WorldCat Subject Intelligence

**Priority:** 🟢 MEDIUM
**Issue Type:** Feature Enhancement
**Created:** 2026-02-02
**Status:** ✅ COMPLETE

---

## 1. Overview

Enhance the library module to capture rich MARC subject data from WorldCat, build a local subject authority over time, and provide AI-powered subject suggestions based on NER entities and usage patterns.

---

## 2. Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Enhanced WorldCat MARC Parsing | ✅ Done | 6XX fields with subdivisions |
| Database Schema | ✅ Done | 2 new tables + ALTER |
| SubjectAuthorityRepository | ✅ Done | CRUD + search methods |
| SubjectSuggestionService | ✅ Done | Weighted scoring algorithm |
| editAction Integration | ✅ Done | Authority + NER hooks |
| suggestSubjects API | ✅ Done | AJAX endpoint |
| Routing Configuration | ✅ Done | `/library/suggestSubjects` |
| UI Suggest Button | ✅ Done | Modal with ranked suggestions |
| IsbnMetadataMapper | ✅ Done | Enhanced subject handling |
| Database Migration | ✅ Done | Verified on archive database |

---

## 3. Database Schema

### New Tables

#### `library_subject_authority`
Stores controlled subject headings with usage tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| heading | VARCHAR(500) | Subject heading text |
| heading_normalized | VARCHAR(500) | Normalized form for matching |
| heading_type | ENUM | topical, personal, corporate, geographic, genre, meeting |
| source | VARCHAR(50) | Source vocabulary (lcsh, mesh, local, etc.) |
| lcsh_id | VARCHAR(100) | Authority record ID (e.g., sh85034652) |
| lcsh_uri | VARCHAR(500) | Full URI to authority record |
| suggested_dewey | VARCHAR(50) | Suggested Dewey classification |
| suggested_lcc | VARCHAR(50) | Suggested LCC classification |
| broader_terms | JSON | Parent/broader subject terms |
| narrower_terms | JSON | Child/narrower subject terms |
| related_terms | JSON | Related subject terms |
| usage_count | INT UNSIGNED | Number of times used in catalog |
| first_used_at | TIMESTAMP | When first used |
| last_used_at | TIMESTAMP | When last used |

**Indexes:**
- `uk_heading` - UNIQUE (heading_normalized, heading_type, source)
- `idx_usage` - (usage_count DESC)
- `ft_heading` - FULLTEXT (heading)

#### `library_entity_subject_map`
Bridges NER entities to subject authorities for suggestion cross-referencing.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| entity_type | VARCHAR(50) | NER entity type (PERSON, ORG, GPE, etc.) |
| entity_value | VARCHAR(500) | Original entity value |
| entity_normalized | VARCHAR(500) | Normalized for matching |
| subject_authority_id | BIGINT UNSIGNED | FK to subject authority |
| co_occurrence_count | INT UNSIGNED | Times entity appeared with subject |
| confidence | DECIMAL(5,4) | Confidence score (0-1) |

### Modified Table

#### `library_item_subject` - Added Columns
| Column | Type | Description |
|--------|------|-------------|
| lcsh_id | VARCHAR(100) | LCSH authority ID |
| authority_id | BIGINT UNSIGNED | FK to library_subject_authority |
| dewey_number | VARCHAR(50) | Dewey classification |
| lcc_number | VARCHAR(50) | LCC classification |
| subdivisions | JSON | MARC subdivisions ($x, $y, $z, $v) |

---

## 4. Files Modified/Created

### Modified Files

| File | Changes |
|------|---------|
| `lib/Service/WorldCatService.php` | Added `extractSubjectHeadings()` and `extractClassifications()` methods |
| `database/install.sql` | Appended new tables and ALTER statements |
| `modules/library/actions/editAction.class.php` | Added authority hooks after saveSubjects() |
| `config/ahgLibraryPluginConfiguration.class.php` | Added `library_suggest_subjects` route |
| `modules/library/templates/editSuccess.php` | Added Suggest button and modal JavaScript |
| `lib/Service/IsbnMetadataMapper.php` | Enhanced subject structure handling |

### New Files

| File | Purpose |
|------|---------|
| `lib/Repository/SubjectAuthorityRepository.php` | Authority CRUD, search, NER linking |
| `lib/Service/SubjectSuggestionService.php` | AI-powered suggestion algorithm |
| `modules/library/actions/suggestSubjectsAction.class.php` | AJAX API endpoint |

---

## 5. API Reference

### Endpoint: `/library/suggestSubjects`

**Method:** GET
**Authentication:** Required

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| title | string | No | Item title for text matching |
| description | string | No | Item description/summary |
| object_id | int | No | Information object ID (for NER lookup) |
| existing_subjects | JSON | No | Already assigned subjects to exclude |

**Response:**
```json
{
  "success": true,
  "suggestions": [
    {
      "id": 123,
      "heading": "History",
      "heading_type": "topical",
      "source": "lcsh",
      "lcsh_id": "sh85034652",
      "lcsh_uri": "http://id.loc.gov/authorities/subjects/sh85034652",
      "usage_count": 45,
      "score": 0.8234,
      "ner_source": false
    }
  ],
  "meta": {
    "ner_entity_count": 5,
    "existing_count": 2
  }
}
```

---

## 6. Suggestion Algorithm

The `SubjectSuggestionService` uses weighted scoring:

| Factor | Weight | Description |
|--------|--------|-------------|
| Text Match | 40% | FULLTEXT search on title + description |
| NER Cross-Reference | 30% | Entity-subject co-occurrence from historical data |
| Usage Frequency | 30% | How often the subject is used in the catalog |

### Scoring Formula
```
score = (text_relevance * 0.40) + (ner_confidence * co_occurrence * 0.30) + (usage_normalized * 0.30)
```

Results are ranked by score and top 15 returned.

---

## 7. MARC Field Mapping

### Subject Fields (6XX)
| MARC Tag | Heading Type |
|----------|--------------|
| 600 | Personal name |
| 610 | Corporate name |
| 611 | Meeting name |
| 650 | Topical term |
| 651 | Geographic name |
| 655 | Genre/form |

### Subfield Mapping
| Subfield | Purpose |
|----------|---------|
| $a | Main heading |
| $x | General subdivision |
| $y | Chronological subdivision |
| $z | Geographic subdivision |
| $v | Form subdivision |
| $0 | Authority record ID |
| $2 | Source of heading |

### Thesaurus Indicators (2nd indicator)
| Value | Source |
|-------|--------|
| 0 | Library of Congress Subject Headings (LCSH) |
| 1 | LC Children's literature |
| 2 | Medical Subject Headings (MeSH) |
| 3 | National Agricultural Library |
| 5 | Canadian Subject Headings |
| 6 | Répertoire de vedettes-matière |
| 7 | Source specified in $2 |

### Classification Fields
| MARC Tag | Classification |
|----------|---------------|
| 050 | Library of Congress (LCC) |
| 082 | Dewey Decimal (DDC) |
| 090 | Local LCC |
| 092 | Local Dewey |

---

## 8. Usage

### From UI
1. Navigate to Library Edit form
2. Click the **Suggest** button (yellow, with magic wand icon)
3. Modal displays ranked suggestions with:
   - Heading text
   - Type badge (Topical, Personal, etc.)
   - Score percentage
   - Usage count
   - NER indicator (brain icon) if matched from entities
4. Select desired subjects via checkboxes
5. Click **Add Selected**

### Programmatic Usage
```php
require_once sfConfig::get('sf_plugins_dir') . '/ahgLibraryPlugin/lib/Service/SubjectSuggestionService.php';

$service = new \ahgLibraryPlugin\Service\SubjectSuggestionService();

$suggestions = $service->suggest([
    'title' => 'The History of Ancient Rome',
    'description' => 'A comprehensive study of Roman civilization...',
    'ner_entities' => [
        ['type' => 'GPE', 'value' => 'Rome'],
        ['type' => 'ORG', 'value' => 'Roman Empire'],
    ],
    'existing_subjects' => ['Ancient history'],
]);
```

---

## 9. Testing Checklist

- [x] Database: Run migration, verify tables created
- [x] Verify `library_subject_authority` table structure
- [x] Verify `library_entity_subject_map` table structure
- [x] Verify `library_item_subject` new columns
- [x] Verify foreign key constraints
- [x] Verify FULLTEXT index on heading
- [ ] WorldCat: Test ISBN lookup returns enhanced subjects with authority IDs
- [ ] Authority: Catalog a book, verify `library_subject_authority` populated
- [ ] Suggestion API: Call `/library/suggestSubjects` with title text
- [ ] NER Bridge: Catalog book with NER entities, verify `library_entity_subject_map` populated
- [ ] UI: Click Suggest button, verify modal shows ranked suggestions
- [ ] End-to-end: New book → suggestions appear → add subject → authority count increments

---

## 10. Future Enhancements

- [ ] Fetch broader/narrower/related terms from id.loc.gov API
- [ ] Auto-suggest classifications based on subject headings
- [ ] Subject authority report page
- [ ] Bulk subject import from LCSH authority files
- [ ] Subject usage analytics dashboard
