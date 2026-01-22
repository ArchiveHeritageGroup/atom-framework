<?php

declare(strict_types=1);

namespace AtomFramework\Services\SemanticSearch;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * WordNet Sync Service
 *
 * Synchronizes thesaurus data from WordNet via the Datamuse API.
 * Datamuse provides free access to synonym data based on WordNet lexical database.
 *
 * API Documentation: https://www.datamuse.com/api/
 *
 * @package AtomFramework\Services\SemanticSearch
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class WordNetSyncService
{
    private Logger $logger;
    private ThesaurusService $thesaurus;
    private array $config;

    private const API_BASE = 'https://api.datamuse.com';
    private const DEFAULT_RATE_LIMIT_MS = 100;

    // Comprehensive archival domain terms
    public const ARCHIVAL_TERMS = [
        // Core archival concepts
        'archive', 'archives', 'repository', 'record', 'records', 'document', 'documents',
        'fonds', 'collection', 'series', 'file', 'item', 'accession', 'acquisition',
        'arrangement', 'description', 'finding aid', 'inventory', 'catalog', 'catalogue',
        'registry', 'register', 'depot', 'holdings', 'papers', 'manuscripts',

        // Document types
        'letter', 'correspondence', 'memo', 'memorandum', 'report', 'minute', 'minutes',
        'agenda', 'resolution', 'decree', 'ordinance', 'regulation', 'policy',
        'contract', 'agreement', 'deed', 'will', 'testament', 'certificate',
        'license', 'permit', 'warrant', 'summons', 'subpoena', 'affidavit',
        'petition', 'application', 'form', 'questionnaire', 'survey',
        'invoice', 'receipt', 'voucher', 'ledger', 'journal', 'account',
        'budget', 'estimate', 'quotation', 'tender', 'bid', 'proposal',
        'plan', 'drawing', 'blueprint', 'sketch', 'diagram', 'chart', 'graph',
        'map', 'atlas', 'survey', 'plat', 'cadastral',
        'photograph', 'photo', 'picture', 'image', 'portrait', 'snapshot',
        'negative', 'slide', 'transparency', 'print', 'proof',
        'film', 'movie', 'video', 'recording', 'tape', 'reel',
        'audio', 'sound', 'voice', 'speech', 'interview', 'oral history',
        'newspaper', 'clipping', 'cutting', 'article', 'press release',
        'pamphlet', 'brochure', 'leaflet', 'flyer', 'poster', 'broadside',
        'book', 'volume', 'tome', 'publication', 'periodical', 'serial',
        'diary', 'journal', 'logbook', 'notebook', 'scrapbook', 'album',
        'thesis', 'dissertation', 'essay', 'paper', 'monograph', 'treatise',
        'manual', 'handbook', 'guide', 'instruction', 'specification',
        'catalog', 'directory', 'index', 'list', 'roster', 'roll',
        'transcript', 'minutes', 'proceedings', 'testimony', 'deposition',

        // Preservation & conservation
        'preservation', 'conservation', 'restoration', 'digitization', 'migration',
        'storage', 'vault', 'climate control', 'deacidification', 'encapsulation',
        'repair', 'binding', 'rebinding', 'lamination', 'microfilm', 'microform',

        // Access & reference
        'access', 'reference', 'research', 'researcher', 'patron', 'user', 'visitor',
        'reading room', 'consultation', 'reproduction', 'copy', 'duplicate',
        'retrieval', 'request', 'order', 'loan', 'circulation',

        // Descriptive elements
        'provenance', 'custody', 'creator', 'author', 'donor', 'depositor',
        'scope', 'content', 'extent', 'date', 'restriction', 'embargo',
        'title', 'name', 'subject', 'topic', 'theme', 'keyword',
        'abstract', 'summary', 'description', 'annotation', 'note',

        // Physical storage
        'box', 'folder', 'container', 'shelf', 'stack', 'cabinet', 'drawer',
        'carton', 'case', 'envelope', 'sleeve', 'wrapper', 'cover',

        // Digital
        'digital', 'electronic', 'born digital', 'metadata', 'format',
        'database', 'software', 'hardware', 'server', 'storage', 'backup',
        'scan', 'scanner', 'OCR', 'transcription', 'encoding',

        // People & organizations
        'archivist', 'records manager', 'curator', 'librarian', 'historian',
        'researcher', 'scholar', 'student', 'academic', 'professor',
        'government', 'agency', 'department', 'ministry', 'office', 'bureau',
        'company', 'corporation', 'business', 'firm', 'enterprise', 'organization',
        'association', 'society', 'club', 'union', 'federation', 'council',
        'church', 'parish', 'diocese', 'congregation', 'mission',
        'school', 'college', 'university', 'institution', 'academy',
        'hospital', 'clinic', 'asylum', 'sanatorium',
        'court', 'tribunal', 'commission', 'committee', 'board',
        'family', 'estate', 'household', 'person', 'individual',
    ];

    // Comprehensive library terms
    public const LIBRARY_TERMS = [
        // Core library concepts
        'library', 'book', 'periodical', 'journal', 'magazine', 'newspaper',
        'bibliography', 'bibliographic', 'catalog', 'classification',
        'subject', 'heading', 'authority', 'call number', 'shelf mark',
        'circulation', 'loan', 'borrower', 'patron', 'reader',
        'reference', 'interlibrary loan', 'acquisition', 'collection development',
        'serials', 'monograph', 'anthology', 'manuscript', 'rare book',

        // Book parts & types
        'chapter', 'section', 'volume', 'edition', 'printing', 'issue',
        'preface', 'foreword', 'introduction', 'appendix', 'index', 'glossary',
        'fiction', 'nonfiction', 'novel', 'biography', 'autobiography', 'memoir',
        'poetry', 'drama', 'prose', 'literature', 'text', 'textbook',
        'encyclopedia', 'dictionary', 'thesaurus', 'almanac', 'yearbook',
        'atlas', 'gazetteer', 'handbook', 'manual', 'guide',

        // Publishing
        'publisher', 'author', 'editor', 'compiler', 'translator', 'illustrator',
        'copyright', 'ISBN', 'ISSN', 'imprint', 'colophon',
        'hardcover', 'paperback', 'binding', 'cover', 'jacket', 'spine',

        // Reading & literacy
        'reading', 'literacy', 'literature', 'reader', 'bookworm',
        'story', 'narrative', 'tale', 'fable', 'legend', 'myth',
    ];

    // Comprehensive museum terms
    public const MUSEUM_TERMS = [
        // Core museum concepts
        'museum', 'gallery', 'exhibition', 'exhibit', 'display', 'showcase',
        'artifact', 'artefact', 'object', 'specimen', 'artwork', 'piece',
        'curator', 'curation', 'collection', 'accession', 'deaccession',
        'provenance', 'acquisition', 'donation', 'bequest', 'loan', 'gift',
        'conservation', 'restoration', 'preservation', 'storage', 'depot',
        'catalogue', 'inventory', 'registration', 'documentation', 'label',

        // Art types
        'painting', 'sculpture', 'drawing', 'print', 'engraving', 'etching',
        'lithograph', 'woodcut', 'watercolor', 'oil', 'acrylic', 'pastel',
        'portrait', 'landscape', 'still life', 'abstract', 'figurative',
        'ceramic', 'pottery', 'porcelain', 'glass', 'metalwork', 'jewelry',
        'textile', 'tapestry', 'embroidery', 'weaving', 'costume', 'fashion',
        'furniture', 'decorative arts', 'applied arts', 'craft', 'handicraft',

        // Natural history
        'fossil', 'mineral', 'rock', 'gem', 'crystal', 'meteorite',
        'skeleton', 'bone', 'skull', 'shell', 'specimen', 'taxidermy',
        'plant', 'botanical', 'herbarium', 'flora', 'fauna', 'wildlife',
        'insect', 'butterfly', 'bird', 'mammal', 'reptile', 'fish',

        // Archaeology
        'archaeology', 'excavation', 'dig', 'site', 'ruin', 'remains',
        'pottery', 'shard', 'fragment', 'tool', 'weapon', 'implement',
        'coin', 'medal', 'seal', 'stamp', 'inscription', 'tablet',
        'tomb', 'burial', 'grave', 'mummy', 'sarcophagus', 'coffin',

        // Display & presentation
        'pedestal', 'plinth', 'mount', 'frame', 'case', 'vitrine',
        'diorama', 'model', 'replica', 'reproduction', 'cast', 'mold',
    ];

    // General vocabulary - common words for document content
    public const GENERAL_TERMS = [
        // Time periods
        'ancient', 'medieval', 'modern', 'contemporary', 'historical', 'prehistoric',
        'century', 'decade', 'year', 'month', 'day', 'era', 'epoch', 'period', 'age',
        'past', 'present', 'future', 'old', 'new', 'recent', 'early', 'late',

        // Actions & processes
        'create', 'build', 'construct', 'make', 'produce', 'manufacture',
        'destroy', 'demolish', 'damage', 'ruin', 'wreck', 'break',
        'change', 'modify', 'alter', 'transform', 'convert', 'adapt',
        'grow', 'develop', 'expand', 'increase', 'extend', 'enlarge',
        'reduce', 'decrease', 'shrink', 'diminish', 'decline', 'contract',
        'begin', 'start', 'commence', 'initiate', 'launch', 'open',
        'end', 'finish', 'complete', 'conclude', 'close', 'terminate',
        'move', 'transfer', 'transport', 'ship', 'deliver', 'send',
        'receive', 'accept', 'obtain', 'acquire', 'get', 'take',
        'give', 'donate', 'grant', 'provide', 'supply', 'offer',
        'buy', 'purchase', 'acquire', 'procure', 'obtain',
        'sell', 'trade', 'exchange', 'barter', 'deal',
        'work', 'labor', 'toil', 'operate', 'function', 'perform',
        'meet', 'gather', 'assemble', 'convene', 'congregate',
        'discuss', 'debate', 'argue', 'negotiate', 'confer',
        'decide', 'determine', 'resolve', 'settle', 'conclude',
        'agree', 'consent', 'approve', 'accept', 'confirm',
        'refuse', 'reject', 'decline', 'deny', 'oppose',
        'write', 'record', 'document', 'note', 'register', 'log',
        'read', 'study', 'examine', 'review', 'analyze', 'investigate',
        'find', 'discover', 'locate', 'identify', 'detect', 'uncover',
        'lose', 'misplace', 'missing', 'lost', 'disappeared',
        'protect', 'preserve', 'safeguard', 'secure', 'defend', 'guard',
        'attack', 'assault', 'invade', 'raid', 'strike', 'bomb',
        'kill', 'murder', 'execute', 'assassinate', 'slay',
        'die', 'death', 'deceased', 'passed', 'expire', 'perish',
        'born', 'birth', 'born', 'native', 'origin',
        'marry', 'wedding', 'marriage', 'wed', 'spouse', 'bride', 'groom',
        'divorce', 'separation', 'annulment',

        // Places & geography
        'place', 'location', 'site', 'area', 'region', 'zone', 'district',
        'country', 'nation', 'state', 'province', 'territory', 'colony',
        'city', 'town', 'village', 'hamlet', 'settlement', 'community',
        'street', 'road', 'avenue', 'lane', 'drive', 'highway',
        'building', 'structure', 'edifice', 'construction',
        'house', 'home', 'residence', 'dwelling', 'abode',
        'farm', 'ranch', 'plantation', 'estate', 'property', 'land',
        'river', 'stream', 'creek', 'lake', 'pond', 'ocean', 'sea',
        'mountain', 'hill', 'valley', 'plain', 'plateau', 'desert',
        'forest', 'wood', 'jungle', 'bush', 'veld', 'savanna',
        'north', 'south', 'east', 'west', 'central', 'coastal', 'inland',

        // People & society
        'person', 'people', 'individual', 'human', 'man', 'woman', 'child',
        'family', 'parent', 'father', 'mother', 'son', 'daughter', 'sibling',
        'brother', 'sister', 'uncle', 'aunt', 'cousin', 'nephew', 'niece',
        'grandfather', 'grandmother', 'grandparent', 'ancestor', 'descendant',
        'friend', 'companion', 'colleague', 'associate', 'partner',
        'enemy', 'opponent', 'rival', 'adversary', 'foe',
        'leader', 'chief', 'head', 'director', 'manager', 'supervisor',
        'worker', 'employee', 'staff', 'laborer', 'servant', 'slave',
        'owner', 'proprietor', 'master', 'employer', 'boss',
        'teacher', 'instructor', 'professor', 'educator', 'tutor',
        'student', 'pupil', 'learner', 'apprentice', 'trainee',
        'doctor', 'physician', 'surgeon', 'nurse', 'medic',
        'lawyer', 'attorney', 'advocate', 'solicitor', 'barrister',
        'judge', 'magistrate', 'justice', 'arbitrator',
        'police', 'constable', 'officer', 'detective', 'inspector',
        'soldier', 'military', 'army', 'navy', 'air force', 'marine',
        'priest', 'minister', 'pastor', 'reverend', 'bishop', 'pope',
        'king', 'queen', 'prince', 'princess', 'duke', 'lord', 'noble',
        'president', 'prime minister', 'governor', 'mayor', 'councillor',
        'merchant', 'trader', 'businessman', 'entrepreneur', 'shopkeeper',
        'farmer', 'rancher', 'peasant', 'laborer', 'miner',
        'artist', 'painter', 'sculptor', 'musician', 'composer', 'singer',
        'writer', 'author', 'poet', 'journalist', 'reporter', 'editor',
        'architect', 'engineer', 'builder', 'contractor', 'craftsman',

        // Events & occasions
        'event', 'occasion', 'ceremony', 'celebration', 'festival', 'holiday',
        'meeting', 'conference', 'congress', 'convention', 'assembly',
        'election', 'vote', 'referendum', 'poll', 'ballot',
        'war', 'battle', 'conflict', 'fight', 'combat', 'struggle',
        'peace', 'treaty', 'agreement', 'armistice', 'ceasefire',
        'revolution', 'rebellion', 'uprising', 'revolt', 'coup',
        'independence', 'liberation', 'freedom', 'emancipation',
        'disaster', 'catastrophe', 'calamity', 'tragedy', 'crisis',
        'fire', 'flood', 'earthquake', 'storm', 'drought', 'famine',
        'epidemic', 'pandemic', 'plague', 'disease', 'illness',
        'accident', 'incident', 'mishap', 'collision', 'crash',

        // Concepts & ideas
        'law', 'rule', 'regulation', 'statute', 'act', 'decree', 'ordinance',
        'right', 'privilege', 'freedom', 'liberty', 'justice', 'equality',
        'duty', 'obligation', 'responsibility', 'liability',
        'power', 'authority', 'control', 'influence', 'dominion',
        'money', 'currency', 'cash', 'funds', 'capital', 'finance',
        'price', 'cost', 'value', 'worth', 'fee', 'charge',
        'tax', 'duty', 'levy', 'tariff', 'toll', 'tribute',
        'debt', 'loan', 'credit', 'mortgage', 'interest',
        'profit', 'gain', 'income', 'revenue', 'earnings',
        'loss', 'deficit', 'expense', 'cost', 'expenditure',
        'trade', 'commerce', 'business', 'industry', 'economy',
        'agriculture', 'farming', 'cultivation', 'harvest', 'crop',
        'mining', 'extraction', 'quarry', 'ore', 'mineral',
        'manufacture', 'production', 'factory', 'industry', 'mill',
        'transport', 'shipping', 'railway', 'railroad', 'aviation',
        'communication', 'telegraph', 'telephone', 'radio', 'television',
        'education', 'learning', 'knowledge', 'wisdom', 'intelligence',
        'science', 'research', 'experiment', 'discovery', 'invention',
        'technology', 'innovation', 'progress', 'development', 'advancement',
        'religion', 'faith', 'belief', 'worship', 'prayer', 'ritual',
        'culture', 'tradition', 'custom', 'heritage', 'legacy',
        'art', 'music', 'literature', 'drama', 'dance', 'performance',
        'sport', 'game', 'competition', 'tournament', 'championship',

        // Descriptive terms
        'important', 'significant', 'major', 'principal', 'chief', 'main',
        'minor', 'small', 'little', 'slight', 'trivial', 'insignificant',
        'large', 'big', 'great', 'huge', 'vast', 'enormous', 'massive',
        'good', 'excellent', 'fine', 'superior', 'outstanding', 'exceptional',
        'bad', 'poor', 'inferior', 'deficient', 'inadequate', 'unsatisfactory',
        'true', 'correct', 'accurate', 'right', 'valid', 'authentic',
        'false', 'incorrect', 'wrong', 'inaccurate', 'invalid', 'fake',
        'official', 'formal', 'authorized', 'certified', 'registered',
        'unofficial', 'informal', 'unauthorized', 'unregistered',
        'public', 'open', 'accessible', 'available', 'free',
        'private', 'confidential', 'secret', 'classified', 'restricted',
        'local', 'regional', 'national', 'international', 'global', 'worldwide',
        'urban', 'rural', 'suburban', 'metropolitan', 'municipal',
        'civil', 'civilian', 'military', 'naval', 'maritime',
        'domestic', 'foreign', 'overseas', 'colonial', 'imperial',
        'native', 'indigenous', 'aboriginal', 'local', 'resident',
        'immigrant', 'emigrant', 'migrant', 'refugee', 'settler',
        'white', 'black', 'colored', 'african', 'european', 'asian',
        'rich', 'wealthy', 'affluent', 'prosperous', 'poor', 'impoverished',
        'young', 'old', 'elderly', 'aged', 'juvenile', 'adult',
        'male', 'female', 'masculine', 'feminine',
        'single', 'married', 'widowed', 'divorced',
        'alive', 'living', 'dead', 'deceased', 'late',
    ];

    // South African specific terms
    public const SOUTH_AFRICAN_TERMS = [
        // Apartheid era
        'apartheid', 'segregation', 'discrimination', 'racism', 'oppression',
        'homeland', 'bantustan', 'reserve', 'location', 'township',
        'pass', 'dompas', 'passbook', 'reference book', 'influx control',
        'group areas', 'forced removal', 'resettlement', 'relocation',
        'bantustans', 'transkei', 'bophuthatswana', 'venda', 'ciskei',
        'petty apartheid', 'grand apartheid', 'separate development',
        'job reservation', 'color bar', 'racial classification',
        'immorality act', 'mixed marriages act', 'population registration',

        // Resistance & liberation
        'resistance', 'liberation', 'struggle', 'freedom', 'democracy',
        'protest', 'demonstration', 'march', 'boycott', 'strike', 'stayaway',
        'defiance', 'civil disobedience', 'passive resistance', 'sabotage',
        'underground', 'exile', 'banned', 'banished', 'detained', 'imprisoned',
        'treason trial', 'rivonia trial', 'robben island', 'political prisoner',
        'ANC', 'PAC', 'SACP', 'UDF', 'COSATU', 'BCM', 'black consciousness',
        'sharpeville', 'soweto', 'uprising', 'massacre', 'shootings',
        'state of emergency', 'unrest', 'violence', 'conflict',

        // Transition & reconciliation
        'transition', 'negotiation', 'CODESA', 'constitution', 'election',
        'TRC', 'truth commission', 'reconciliation', 'amnesty', 'testimony',
        'gross human rights violation', 'victim', 'perpetrator', 'reparation',
        'new south africa', 'rainbow nation', 'democracy', 'transformation',

        // Colonial era
        'colonial', 'colony', 'settler', 'boer', 'afrikaner', 'voortrekker',
        'great trek', 'trekker', 'laager', 'commando', 'burgher',
        'VOC', 'dutch east india', 'cape colony', 'natal', 'transvaal', 'orange free state',
        'british', 'english', 'uitlander', 'imperial', 'crown colony',
        'anglo boer war', 'south african war', 'concentration camp',
        'zulu', 'xhosa', 'sotho', 'tswana', 'venda', 'ndebele', 'swazi', 'tsonga',
        'chief', 'king', 'inkosi', 'kgosi', 'paramount chief', 'tribal authority',
        'native', 'bantu', 'african', 'black', 'non-white', 'non-european',

        // Places
        'cape town', 'johannesburg', 'pretoria', 'durban', 'port elizabeth',
        'bloemfontein', 'pietermaritzburg', 'kimberley', 'east london',
        'soweto', 'alexandra', 'khayelitsha', 'gugulethu', 'langa',
        'district six', 'sophiatown', 'cato manor',
        'kruger', 'drakensberg', 'table mountain', 'karoo', 'bushveld',

        // Institutions
        'parliament', 'union buildings', 'senate', 'provincial council',
        'magistrate', 'native commissioner', 'bantu administration',
        'SAP', 'SADF', 'security police', 'bureau of state security',
        'SABC', 'publications control', 'censorship',

        // Culture & society
        'afrikaans', 'english', 'zulu', 'xhosa', 'sotho', 'tswana',
        'ndebele', 'venda', 'tsonga', 'swazi', 'pedi',
        'braai', 'biltong', 'boerewors', 'potjiekos', 'bobotie',
        'rooibos', 'protea', 'springbok', 'rugby', 'cricket',
        'rand', 'krugerrand', 'mining', 'gold', 'diamond', 'platinum',
        'wine', 'vineyard', 'winelands', 'stellenbosch', 'franschhoek',
    ];

    // Historical periods and eras
    public const HISTORICAL_TERMS = [
        // Time periods
        'prehistoric', 'ancient', 'classical', 'medieval', 'renaissance',
        'reformation', 'enlightenment', 'industrial', 'modern', 'contemporary',
        'victorian', 'edwardian', 'interwar', 'postwar', 'cold war',

        // World events
        'world war', 'great war', 'second world war', 'holocaust', 'genocide',
        'depression', 'recession', 'boom', 'bust', 'crisis',
        'revolution', 'civil war', 'independence', 'colonization', 'decolonization',
        'imperialism', 'nationalism', 'communism', 'socialism', 'capitalism',
        'fascism', 'nazism', 'totalitarianism', 'dictatorship', 'democracy',

        // Historical figures (generic)
        'monarch', 'emperor', 'czar', 'sultan', 'pharaoh', 'chief',
        'dictator', 'tyrant', 'despot', 'autocrat', 'ruler',
        'conqueror', 'invader', 'colonizer', 'liberator', 'revolutionary',
        'statesman', 'diplomat', 'ambassador', 'envoy', 'consul',
        'pioneer', 'explorer', 'discoverer', 'adventurer', 'settler',
        'reformer', 'activist', 'campaigner', 'advocate', 'champion',
        'martyr', 'hero', 'heroine', 'patriot', 'traitor',
    ];

    public function __construct(?ThesaurusService $thesaurus = null, array $config = [])
    {
        $logDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';

        $this->config = array_merge([
            'log_path' => $logDir . '/wordnet_sync.log',
            'rate_limit_ms' => self::DEFAULT_RATE_LIMIT_MS,
            'max_synonyms_per_term' => 10,
            'min_score' => 50000,
            'timeout' => 10,
        ], $config);

        $this->logger = new Logger('wordnet_sync');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }

        $this->thesaurus = $thesaurus ?? new ThesaurusService();
    }

    // ========================================================================
    // Sync Operations
    // ========================================================================

    /**
     * Sync synonyms for archival domain terms
     */
    public function syncArchivalTerms(): array
    {
        return $this->syncTerms(self::ARCHIVAL_TERMS, ThesaurusService::DOMAIN_ARCHIVAL);
    }

    /**
     * Sync synonyms for library domain terms
     */
    public function syncLibraryTerms(): array
    {
        return $this->syncTerms(self::LIBRARY_TERMS, ThesaurusService::DOMAIN_LIBRARY);
    }

    /**
     * Sync synonyms for museum domain terms
     */
    public function syncMuseumTerms(): array
    {
        return $this->syncTerms(self::MUSEUM_TERMS, ThesaurusService::DOMAIN_MUSEUM);
    }

    /**
     * Sync synonyms for general vocabulary terms
     */
    public function syncGeneralTerms(): array
    {
        return $this->syncTerms(self::GENERAL_TERMS, ThesaurusService::DOMAIN_GENERAL);
    }

    /**
     * Sync synonyms for South African specific terms
     */
    public function syncSouthAfricanTerms(): array
    {
        return $this->syncTerms(self::SOUTH_AFRICAN_TERMS, ThesaurusService::DOMAIN_GENERAL);
    }

    /**
     * Sync synonyms for historical terms
     */
    public function syncHistoricalTerms(): array
    {
        return $this->syncTerms(self::HISTORICAL_TERMS, ThesaurusService::DOMAIN_GENERAL);
    }

    /**
     * Sync a list of terms
     */
    public function syncTerms(array $terms, string $domain = ThesaurusService::DOMAIN_GENERAL): array
    {
        $logId = $this->thesaurus->createSyncLog(ThesaurusService::SOURCE_WORDNET, 'term_specific');

        $this->thesaurus->updateSyncLog($logId, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $stats = [
            'terms_processed' => 0,
            'terms_added' => 0,
            'terms_updated' => 0,
            'synonyms_added' => 0,
            'errors' => [],
        ];

        foreach ($terms as $term) {
            try {
                $result = $this->syncTerm($term, $domain);
                $stats['terms_processed']++;

                if ($result['term_id']) {
                    if ($result['is_new']) {
                        $stats['terms_added']++;
                    } else {
                        $stats['terms_updated']++;
                    }
                    $stats['synonyms_added'] += $result['synonyms_added'];
                }

                // Rate limiting
                usleep($this->config['rate_limit_ms'] * 1000);

            } catch (\Exception $e) {
                $stats['errors'][] = "{$term}: " . $e->getMessage();
                $this->logger->error("Failed to sync term: {$term}", ['error' => $e->getMessage()]);
            }
        }

        $this->thesaurus->updateSyncLog($logId, [
            'status' => empty($stats['errors']) ? 'completed' : 'completed_with_errors',
            'completed_at' => date('Y-m-d H:i:s'),
            'terms_processed' => $stats['terms_processed'],
            'terms_added' => $stats['terms_added'],
            'terms_updated' => $stats['terms_updated'],
            'synonyms_added' => $stats['synonyms_added'],
            'errors' => !empty($stats['errors']) ? json_encode($stats['errors']) : null,
        ]);

        $this->logger->info('WordNet sync completed', $stats);

        return $stats;
    }

    /**
     * Sync a single term
     */
    public function syncTerm(string $term, string $domain = ThesaurusService::DOMAIN_GENERAL): array
    {
        $result = [
            'term' => $term,
            'term_id' => null,
            'is_new' => false,
            'synonyms_added' => 0,
        ];

        // Fetch synonyms from Datamuse
        $synonyms = $this->fetchSynonyms($term);
        $relatedWords = $this->fetchRelatedWords($term);
        $definitions = $this->fetchDefinitions($term);

        // Check if term already exists
        $existing = $this->thesaurus->findTerm($term, ThesaurusService::SOURCE_WORDNET);
        $result['is_new'] = !$existing;

        // Add/update the term
        $termId = $this->thesaurus->addTerm(
            $term,
            ThesaurusService::SOURCE_WORDNET,
            'en',
            [
                'definition' => $definitions[0] ?? null,
                'domain' => $domain,
            ]
        );

        $result['term_id'] = $termId;

        if (!$termId) {
            return $result;
        }

        // Add synonyms
        foreach ($synonyms as $syn) {
            $weight = $this->calculateWeight($syn['score'] ?? 0);

            $this->thesaurus->addSynonym(
                $termId,
                $syn['word'],
                ThesaurusService::SOURCE_WORDNET,
                ThesaurusService::REL_SYNONYM,
                $weight
            );
            $result['synonyms_added']++;
        }

        // Add related words with lower weight
        foreach ($relatedWords as $related) {
            $weight = $this->calculateWeight($related['score'] ?? 0) * 0.8;

            $this->thesaurus->addSynonym(
                $termId,
                $related['word'],
                ThesaurusService::SOURCE_WORDNET,
                ThesaurusService::REL_RELATED,
                $weight
            );
            $result['synonyms_added']++;
        }

        return $result;
    }

    // ========================================================================
    // Datamuse API Methods
    // ========================================================================

    /**
     * Fetch synonyms from Datamuse API
     */
    public function fetchSynonyms(string $word): array
    {
        $url = self::API_BASE . '/words?' . http_build_query([
            'rel_syn' => $word,
            'max' => $this->config['max_synonyms_per_term'],
        ]);

        $response = $this->apiRequest($url);

        // Filter by minimum score
        return array_filter($response, fn($item) =>
            ($item['score'] ?? 0) >= $this->config['min_score']
        );
    }

    /**
     * Fetch related words (broader meaning)
     */
    public function fetchRelatedWords(string $word): array
    {
        $url = self::API_BASE . '/words?' . http_build_query([
            'rel_trg' => $word,  // Triggered by (commonly associated)
            'max' => 5,
        ]);

        return $this->apiRequest($url);
    }

    /**
     * Fetch definitions
     */
    public function fetchDefinitions(string $word): array
    {
        $url = self::API_BASE . '/words?' . http_build_query([
            'sp' => $word,
            'md' => 'd',  // Include definitions
            'max' => 1,
        ]);

        $response = $this->apiRequest($url);

        if (!empty($response[0]['defs'])) {
            return $response[0]['defs'];
        }

        return [];
    }

    /**
     * Fetch words that sound like (for fuzzy matching)
     */
    public function fetchSoundsLike(string $word): array
    {
        $url = self::API_BASE . '/words?' . http_build_query([
            'sl' => $word,
            'max' => 5,
        ]);

        return $this->apiRequest($url);
    }

    /**
     * Fetch words that are spelled similarly (for typo correction)
     */
    public function fetchSpelledLike(string $word): array
    {
        $url = self::API_BASE . '/words?' . http_build_query([
            'sp' => $word . '*',  // Wildcard for prefix matching
            'max' => 5,
        ]);

        return $this->apiRequest($url);
    }

    /**
     * Make API request
     */
    private function apiRequest(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->config['timeout'],
                'header' => [
                    'User-Agent: AtoM-Framework/1.0 (https://theahg.co.za)',
                    'Accept: application/json',
                ],
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $this->logger->error('API request failed', ['url' => $url, 'error' => $error['message'] ?? 'Unknown']);
            return [];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON response', ['url' => $url]);
            return [];
        }

        return $data;
    }

    /**
     * Calculate weight from Datamuse score
     */
    private function calculateWeight(int $score): float
    {
        // Datamuse scores range roughly 0-100000
        // Normalize to 0.0-1.0 range
        $normalized = min($score / 100000, 1.0);

        // Apply a curve to favor higher scores
        return round(0.5 + ($normalized * 0.5), 2);
    }

    // ========================================================================
    // Batch Operations
    // ========================================================================

    /**
     * Sync all domain terms (comprehensive vocabulary)
     *
     * This syncs all term categories for maximum coverage:
     * - Archival terms (~150 terms)
     * - Library terms (~55 terms)
     * - Museum terms (~65 terms)
     * - General vocabulary (~300+ terms)
     * - South African specific (~120+ terms)
     * - Historical terms (~40 terms)
     */
    public function syncAllDomains(): array
    {
        $results = [
            'archival' => $this->syncArchivalTerms(),
            'library' => $this->syncLibraryTerms(),
            'museum' => $this->syncMuseumTerms(),
            'general' => $this->syncGeneralTerms(),
            'south_african' => $this->syncSouthAfricanTerms(),
            'historical' => $this->syncHistoricalTerms(),
        ];

        $totals = [
            'total_terms_processed' => 0,
            'total_terms_added' => 0,
            'total_terms_updated' => 0,
            'total_synonyms_added' => 0,
            'total_errors' => 0,
        ];

        foreach ($results as $domain => $stats) {
            $totals['total_terms_processed'] += $stats['terms_processed'];
            $totals['total_terms_added'] += $stats['terms_added'];
            $totals['total_terms_updated'] += $stats['terms_updated'] ?? 0;
            $totals['total_synonyms_added'] += $stats['synonyms_added'];
            $totals['total_errors'] += count($stats['errors'] ?? []);
        }

        return array_merge($results, ['totals' => $totals]);
    }

    /**
     * Sync custom terms from array
     */
    public function syncCustomTerms(array $terms, string $domain = ThesaurusService::DOMAIN_GENERAL): array
    {
        return $this->syncTerms($terms, $domain);
    }
}
