const chapters = window.UHLMS_TUTORIAL;
const sourceFiles = window.UHLMS_SOURCE_REFERENCE || [];
const nav = document.querySelector('#chapterNav');
const content = document.querySelector('#lessonContent');
const searchInput = document.querySelector('#searchInput');
const searchResults = document.querySelector('#searchResults');
const completed = new Set(JSON.parse(localStorage.getItem('uhlms-tutorial-progress') || '[]'));
let view = location.hash.startsWith('#file=') ? 'reference' : (localStorage.getItem('uhlms-tutorial-view') || 'reference');
let currentIndex = 0;
const referenceStartIndex = Math.max(0, sourceFiles.findIndex(file => file.path === 'public/index.php'));

const htmlEscape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const normalize = value => value.trim().replace(/\s+/g, ' ');

const humanize = value => String(value || '').replace(/^test_/, '').replace(/([a-z0-9])([A-Z])/g, '$1 $2').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());

function describeMethod(name, file) {
  if (!name) return file.purpose.replace(/\.$/, '');
  const exact = {
    home:'prepare the public home page and its lodging highlights', rooms:'show the guest room catalogue and availability', roomDetail:'show one room type with its amenities and availability',
    reserveForm:'prepare the guest reservation form', reserveSubmit:'validate availability and create a guest reservation safely', track:'look up a reservation without exposing unrelated guest data', trackSecure:'show reservation tracking through a signed link',
    register:'create and verify a new guest account', login:'authenticate a guest account', logout:'end the current guest session', dashboard:'assemble the signed-in guest dashboard',
    initializePayment:'create the external PayMongo checkout flow for an approved reservation', handle:'accept and route an incoming request or external event',
    checkIn:'turn an approved reservation into guests, room assignments, charges, and a check-in snapshot', checkout:'close active room assignments and complete the stay',
    approve:'move a reservation into its approved workflow state and reserve capacity', reject:'record that the reservation cannot proceed',
    form:'define the fields and validation shown in this Filament staff form', table:'define how staff browse, search, filter, and act on these records',
    getPages:'connect this Filament resource to its list, create, view, edit, and custom pages', getRelations:'attach related-record managers to the current Filament resource',
    up:'apply this database schema change when migrations run', down:'reverse this migration safely during rollback',
    boot:'register application behavior while Laravel is starting', booted:'attach model events and global behavior after this Eloquent model starts',
    casts:'convert stored database values into dependable PHP types', definition:'produce valid default attributes for factory-created test records',
    roomTypes:'list every accommodation category that advertises this amenity to staff and guests',
  };
  if (exact[name]) return exact[name];
  if (/^(test_|it_)/.test(name) || file.path.startsWith('tests/')) return `prove that ${humanize(name).toLowerCase()} remains true in UHLMS`;
  if (/^(can|is|has|should)[A-Z_]/.test(name)) return `answer the domain question “${humanize(name)}?” for the calling workflow`;
  if (/^(create|store|save|persist)/i.test(name)) return `${humanize(name).toLowerCase()} and persist the result for the surrounding workflow`;
  if (/^(update|edit|change)/i.test(name)) return `${humanize(name).toLowerCase()} while preserving the record’s business rules`;
  if (/^(delete|remove|purge|expire)/i.test(name)) return `${humanize(name).toLowerCase()} and clean up the affected workflow state`;
  if (/^(get|find|resolve|calculate|compute|build|make)/i.test(name)) return `${humanize(name).toLowerCase()} for its callers`;
  return `${humanize(name).toLowerCase()} as part of ${file.purpose.charAt(0).toLowerCase()}${file.purpose.slice(1).replace(/\.$/, '')}`;
}

function buildLineContexts(file, lines) {
  let currentMethod = null;
  let methodDepth = null;
  let currentSection = null;
  let depth = 0;
  return lines.map(line => {
    const text = line.trim();
    const methodMatch = text.match(/(?:public|protected|private)?\s*(?:static\s+)?function\s+(\w+)/) || text.match(/^(?:export\s+)?(?:async\s+)?function\s+(\w+)/);
    if (methodMatch) {
      currentMethod = methodMatch[1];
      methodDepth = depth;
    }
    if (/\$fillable\s*=\s*\[/.test(text)) currentSection = 'fillable';
    if (currentMethod === 'casts' && /^return\s*\[/.test(text)) currentSection = 'casts';
    const context = { method: currentMethod, goal:describeMethod(currentMethod, file), section:currentSection };
    const opens = (line.match(/\{/g) || []).length;
    const closes = (line.match(/\}/g) || []).length;
    depth += opens - closes;
    if (currentMethod && methodDepth !== null && depth <= methodDepth && closes > 0) {
      currentMethod = null;
      methodDepth = null;
    }
    if (currentSection && /^\];?$/.test(text)) currentSection = null;
    return context;
  });
}

function dependencyReason(importedName, file) {
  const entity = entityNameFor(file);
  if (importedName === 'Model' && entity) return `treat ${entity} as a database-backed lodging record that staff screens, guest pages, services, and reports can share`;
  if (importedName === 'BelongsToMany' && entity === 'Amenity') return 'represent that one amenity can be offered by several room types and one room type can advertise several amenities';
  const reasons = {
    DB:'coordinate database queries and transactions that must succeed or fail together', Log:'record operational evidence when this workflow succeeds or fails',
    Carbon:'compare and calculate reservation, payment, hold, or stay dates consistently', Mail:'send guest-facing workflow messages', Cache:'avoid repeating expensive settings or availability work',
    Request:'receive validated browser or webhook input', ValidationException:'return business-rule failures in Laravel’s standard validation format',
    Reservation:'read or change the central booking record', Room:'work with physical lodging inventory', RoomType:'work with the category and capacity requested by guests',
    Guest:'store people attached to a stay', GuestAccount:'connect reservations and support threads to an authenticated guest identity',
    ReservationPayment:'maintain the payment ledger separately from reservation status', RoomAssignment:'track which guests physically occupy which rooms',
  };
  return reasons[importedName] || `reuse ${importedName} instead of duplicating that responsibility inside ${file.path.split('/').pop()}`;
}

const ENTITY_STORIES = {
  Amenity:'A reusable facilities-catalog item such as Wi-Fi, air-conditioning, a private bathroom, or television. Staff define it once and attach it to every applicable room type; guest room pages then show what a booking includes.',
  Floor:'A physical level of the lodging building. It groups rooms by location so staff can find inventory and guests more easily.',
  RoomType:'A sellable accommodation category such as Standard Room or Dormitory Bed. It carries shared capacity, pricing, amenities, photos, and public visibility while individual Room records represent the physical units.',
  Room:'One physical lodging unit that staff can assign and maintain. Its status reflects whether that real room is available, occupied, held, or unavailable.',
  Reservation:'The central booking file connecting requested dates, guest count, room preferences, approval, payment, check-in, and checkout. Most lodging workflows meet at this record.',
  Guest:'A real person staying under a reservation. Several Guest records can belong to one booking even when only one person made it.',
  GuestAccount:'The online identity used to sign in, claim reservations, see booking history, send support messages, and leave feedback; it is separate from an individual stay occupant.',
  RoomHold:'A temporary claim on room capacity before physical check-in, preventing the same inventory from being promised to two approved reservations.',
  RoomAssignment:'The operational record of which guest occupies which physical room and for what period.',
  ReservationCharge:'One amount the guest owes, such as accommodation, add-ons, penalties, or adjustments. Charges form the debit side of the booking ledger.',
  ReservationPayment:'Money actually received for a reservation, including method, gateway reference, and payment state. It remains separate from charges so balances can be audited.',
  CheckInSnapshot:'A frozen copy of the rates, discounts, guests, rooms, and totals accepted at check-in, preserving what staff and the guest agreed even if prices change later.',
  ReservationLog:'An audit-trail entry recording an important reservation change and who caused it.',
  ReservationRoomRequest:'One requested room category and quantity within a reservation, allowing a booking to ask for more than one room type.',
  ReservationAlternativeOffer:'A time-limited replacement room proposal sent when the original request cannot be fulfilled.',
  ReservationFeedback:'A completed guest’s rating and comments about the lodging experience.',
  Service:'An optional lodging add-on or chargeable service, such as meals, transport, or extra bedding.',
  Setting:'An administrator-controlled operating value used without changing source code, such as guest-site content or payment configuration.',
  SupportInquiry:'A guest help case associated with an account and, when relevant, a reservation.',
  SupportInquiryReply:'One message in the conversation between a guest and lodging staff.',
  TourWaypoint:'One 360-degree scene in the virtual lodging tour, representing a place the visitor can stand and look around.',
  TourHotspot:'An interactive point inside a 360-degree scene that opens information, media, a room, or another waypoint.',
  User:'A staff account for the administration panel, with roles and permissions controlling real operational actions.',
  ForceDeletionLog:'A security audit record created when privileged staff permanently remove protected data.',
};

const COMMON_FIELD_STORIES = {
  name:'The human-readable label staff maintain and guests recognize in screens, filters, emails, and reports.',
  description:'Guest- or staff-facing details that explain what this item provides beyond its short name.',
  is_active:'The operational on/off switch. Staff can retire this item from current choices without destroying historical bookings that already reference it.',
  status:'The current workflow state used to decide what staff and guests may do next.',
  reservation_id:'Links this record back to the booking whose stay, money, guest, request, or audit history it belongs to.',
  room_id:'Identifies the exact physical room affected by this record.',
  room_type_id:'Identifies the accommodation category involved, separate from any eventual physical room assignment.',
  guest_id:'Identifies the actual staying person affected by this record.',
  guest_account_id:'Connects the record to the authenticated guest who owns and may access it online.',
  check_in_date:'The first occupied date used for availability checks, pricing, holds, and arrival planning.',
  check_out_date:'The departure boundary used to release inventory and calculate the number of chargeable nights.',
  guest_count:'The number of people the lodging must safely accommodate.',
  amount:'The monetary value posted to the reservation ledger and used when calculating the remaining balance.',
  price:'The configured selling price used as an input to reservation or service totals.',
  capacity:'The maximum number of guests this accommodation can safely hold.',
  room_number:'The real-world identifier staff use to locate and assign the physical room.',
};

function entityNameFor(file) {
  return file.path.startsWith('app/Models/') ? file.path.split('/').pop().replace('.php', '') : null;
}

function realWorldStory(file) {
  const entity = entityNameFor(file);
  if (entity && ENTITY_STORIES[entity]) return ENTITY_STORIES[entity];
  if (file.path.includes('/Controllers/')) return 'This controller is the reception desk for its web workflow: it receives a guest or staff request, checks it, coordinates the appropriate lodging records and services, then chooses the page or response returned.';
  if (file.path.includes('/Services/')) return 'This service acts like a specialist back-office procedure. It keeps a multi-step lodging rule in one place so public pages, staff actions, jobs, and tests all follow the same process.';
  if (file.path.includes('/Policies/')) return 'This policy acts like an authorization checkpoint at the staff office, deciding whether the signed-in employee may perform a sensitive operation on real lodging data.';
  if (file.path.includes('/Observers/')) return 'This observer is an automatic back-office reaction: whenever the related record changes, it keeps statuses, notifications, logs, or dependent lodging records synchronized.';
  if (file.path.startsWith('database/migrations/')) return 'This migration is a versioned change to the lodging system’s filing structure—the tables and columns that preserve operational history across deployments.';
  if (file.path.endsWith('.blade.php')) return 'This template is a screen, email, or interface fragment that turns lodging records prepared by PHP into information and controls a guest or staff member can use.';
  if (file.path.startsWith('tests/')) return 'This test is a repeatable operational scenario proving that the lodging workflow still behaves correctly after the code changes.';
  return file.purpose;
}

function fileWhyItExists(file) {
  const entity = entityNameFor(file);
  if (entity) return `UHLMS needs one reliable definition of a ${humanize(entity).toLowerCase()} record. This file keeps that lodging concept, its saved information, and its connections to other records in one place so guest pages, staff tools, reports, and automated workflows agree.`;
  const reasons = {
    'HTTP · Controllers':'This file exists to receive a specific browser or integration request and coordinate the correct lodging response. It keeps web-request handling separate from database records and reusable business procedures.',
    'Domain · Services':'This file exists because the workflow changes several related lodging records or is reused from multiple screens. Centralizing it prevents guest pages and staff actions from applying different rules.',
    'Domain · Observers':'This file exists to keep dependent lodging state synchronized automatically whenever a related record changes, regardless of which screen or job caused the change.',
    'Security · Policies':'This file exists to protect a lodging resource at the server level. Hiding a staff button is not enough; every operation must independently verify permission.',
    'Staff UI · Resources':'This file exists to describe how staff create, inspect, search, filter, and operate a lodging record in the Filament administration panel.',
    'Staff UI · Pages & Widgets':'This file exists to provide a focused staff workflow or dashboard view that does not fit ordinary record editing.',
    'Frontend · Blade views':'This file exists to turn server-prepared lodging data into the page, form, email, or interface fragment seen by a guest or staff member.',
    'Frontend · JavaScript':'This file exists for interactions that must happen in the browser, such as the 360° virtual tour, live scene navigation, availability requests, or interface updates.',
    'Database · Migrations':'This file exists to make one traceable change to the permanent lodging database structure and to define how that change can be reversed.',
    'Database · Seed data':'This file exists to create intentional starting inventory, demonstrations, or development records without entering everything manually.',
    'Tests · Unit & support':'This file exists to preserve a focused business rule and detect accidental changes before they reach real lodging data.',
    'Tests · Feature':'This file exists to replay a complete guest, staff, payment, or system journey and prove the visible result remains correct.',
    'Configuration':'This file exists to keep one subsystem’s operating choices separate from business code and adjustable across localhost and Cloudflare environments.',
    'Operations · Jobs':'This file exists to perform reliable background work that should not delay the browser response.',
    'Operations · Commands':'This file exists so scheduled tasks or administrators can maintain lodging state without using a web screen.',
    'Operations · Scripts':'This file exists to start, expose, deploy, or stop the application consistently in a supported environment.',
    'Communication':'This file exists to package a reservation, payment, offer, or support event into a message for the correct guest or staff audience.',
  };
  return reasons[file.category] || `This file provides the project responsibility described below without mixing it into unrelated lodging workflows.`;
}

function fileHowItWorks(file) {
  const methodNames = file.symbols.filter(symbol => symbol.name !== entityNameFor(file)).map(symbol => `<code>${htmlEscape(symbol.name)}()</code>`);
  const named = methodNames.length ? ` Its main entry points are ${methodNames.slice(0, 6).join(', ')}${methodNames.length > 6 ? ` and ${methodNames.length - 6} more` : ''}.` : '';
  const descriptions = {
    'HTTP · Controllers':'Laravel sends a matched route into this controller. The controller validates the request, checks access, asks models or services for the necessary lodging work, then returns a Blade page, redirect, JSON response, or error.',
    'Domain · Models':'Laravel represents each database row as an object from this class. The model identifies saveable fields, converts stored values to useful types, exposes related records, and provides domain queries or helpers.',
    'Domain · Services':'A controller, Filament action, job, or test calls this service with the affected records and input. The service performs the shared rule—often inside a transaction—and returns the result or raises a controlled failure.',
    'Domain · Observers':'Laravel invokes this observer after selected model events. It reacts to the saved or deleted record and recalculates or records related operational state.',
    'Security · Policies':'Laravel and Filament call a method matching the attempted operation. The method evaluates the signed-in staff member’s role, permission, and sometimes the target record, then returns allow or deny.',
    'Staff UI · Resources':'Filament reads this class as a screen blueprint. Its form schema describes editable fields, its table describes staff browsing, and its actions call the project’s models and services.',
    'Staff UI · Pages & Widgets':'Filament mounts this class inside the staff panel. It prepares queries or form state, responds to staff actions, and renders a matching Blade view or widget output.',
    'Frontend · Blade views':'A controller, mail class, or Filament page passes data into this template. Blade evaluates its conditions and loops on the server, escapes displayed values, and produces the final HTML.',
    'Frontend · JavaScript':'Vite loads this module in the browser. It finds the relevant page elements, listens for user interaction, communicates with UHLMS endpoints when needed, and updates the visible interface or 360° scene.',
    'Database · Migrations':'Laravel runs <code>up()</code> during deployment to add or change tables and runs <code>down()</code> during rollback to reverse that versioned change.',
    'Database · Seed data':'Laravel runs this seeder on request. It creates or updates a known set of lodging records, often in dependency order so related floors, room types, rooms, and tours connect correctly.',
    'Tests · Unit & support':'PHPUnit prepares controlled records or mocks, executes one unit of project behavior, and asserts the resulting values, relationships, database state, or authorization decision.',
    'Tests · Feature':'PHPUnit simulates an HTTP request or staff workflow against the application, then checks the response, rendered content, database changes, notifications, or access restrictions.',
    'Configuration':'Laravel loads the returned values during application startup. Project code reads them through <code>config()</code>, while environment variables supply machine-specific values.',
    'Communication':'The workflow creates this mail or notification object with lodging data; the class selects content, recipients, links, and delivery channels before Laravel sends it.',
    'Operations · Jobs':'The queue worker receives the serialized job, reloads its records, performs the background procedure, and records success or failure independently of the original request.',
    'Operations · Commands':'Artisan or the scheduler invokes this command. It queries eligible records, applies the maintenance rule, and reports the result for operations and logs.',
  };
  return `${descriptions[file.category] || file.purpose}${named}`;
}

function fileFlow(file) {
  const flows = {
    'HTTP · Controllers':['Route parameters, form data, authentication, or webhook payload','Validation and coordination with policies, models, and services','HTML page, redirect, JSON response, QR code, file, or HTTP error'],
    'Domain · Models':['A database row or a query from another project layer','Field conversion, relationships, scopes, and domain calculations','A lodging record, related records, or a reusable query result'],
    'Domain · Services':['Validated data plus reservation, room, guest, or payment records','Shared business rules, availability checks, calculations, and transactions','Updated records, calculated results, or a deliberate business-rule error'],
    'Staff UI · Resources':['A staff member’s navigation, search, filters, form data, or action','Filament schema plus authorization and project services','A staff list, form, detail page, notification, or changed lodging record'],
    'Frontend · Blade views':['Lodging data prepared by a controller, mail class, or Filament page','Server-side conditions, loops, reusable partials, and escaped output','The HTML interface or email seen by a guest or staff member'],
    'Frontend · JavaScript':['A page load, click, touch, device movement, or API response','Browser state, event handling, validation, scene rendering, and requests','An updated interface, panorama, hotspot, form, or availability display'],
    'Database · Migrations':['The database schema at the previous application version','A specific table, column, index, constraint, or data-structure change','A schema capable of storing the newer lodging requirement'],
    'Tests · Feature':['A controlled scenario with users, rooms, reservations, dates, or payments','A simulated real request through Laravel','Evidence that the visible workflow and stored result are correct'],
  };
  return flows[file.category] || ['A caller, framework event, configuration value, or stored record',`The file’s responsibility: ${file.purpose.replace(/\.$/,'').toLowerCase()}`,'A value, state change, interface, message, or operational result used elsewhere'];
}

function fieldStory(field, file) {
  const entity = entityNameFor(file);
  const entitySpecific = {
    Amenity:{ name:'The amenity label guests see—such as “Wi-Fi” or “Air-conditioning”—and staff select when configuring room types.', description:'Explains the facility or benefit so guests understand what is included before reserving.', is_active:'Lets staff stop offering an amenity in current room setup while keeping older room and reservation history intact.' },
  };
  return entitySpecific[entity]?.[field] || COMMON_FIELD_STORIES[field] || `Stores the ${humanize(field).toLowerCase()} information needed to represent this ${humanize(entity || 'lodging record').toLowerCase()} in daily operations.`;
}

function operationalExplanation(line, file, number, lines, contexts) {
  const text = line.trim();
  const context = contexts[number - 1] || { method:null, goal:file.purpose, section:null };
  const goal = context.goal.replace(/\.$/, '');
  const entity = entityNameFor(file);
  if (!text || /^[{}()[\],;]+$/.test(text) || /^}\)?[,;]?$/.test(text)) return '';

  const fieldMatch = text.match(/^['"]([^'"]+)['"]\s*(?:=>\s*['"]([^'"]+)['"])?[,]?$/);
  if (fieldMatch && (context.section === 'fillable' || context.section === 'casts')) return fieldStory(fieldMatch[1], file);
  if (text === '<?php') return 'This marks the file as server-side application code. It runs inside the private lodging system rather than being sent to a guest’s browser.';
  if (/^namespace\s+/.test(text)) return `Places this responsibility in the project’s ${htmlEscape(file.category.toLowerCase())} area so Laravel can find it when the lodging workflow needs it.`;
  if (/^use\s+[^({]+;$/.test(text)) {
    const imported = text.replace(/^use\s+|;$/g, '').split('\\').pop();
    return `Connects this file to <code>${htmlEscape(imported)}</code> because the current lodging workflow must ${htmlEscape(dependencyReason(imported, file))}.`;
  }
  if (/^(?:final\s+|abstract\s+)?class\s+/.test(text)) return realWorldStory(file);
  const methodName = text.match(/function\s+(\w+)/)?.[1];
  if (methodName) return `This begins the project procedure that will ${htmlEscape(describeMethod(methodName, file))}. The following lines are one operational unit and should be read as that hotel task.`;
  if (/^protected\s+\$fillable\s*=/.test(text)) return `Defines which details staff- or guest-approved workflows may record for this ${htmlEscape(humanize(entity || 'lodging record').toLowerCase())}. It is the boundary between submitted form data and the official lodging record.`;
  if (/^protected\s+\$casts\s*=|function\s+casts\s*\(/.test(text)) return `Normalizes stored ${htmlEscape(humanize(entity || 'record').toLowerCase())} values before UHLMS uses them for availability, visibility, pricing, or workflow decisions.`;

  if (/Route::(get|post|put|patch|delete)/.test(text)) {
    const verb = text.match(/Route::(\w+)/)?.[1].toUpperCase();
    const url = text.match(/\(['"]([^'"]+)/)?.[1] || '';
    return `Creates the ${verb} ${htmlEscape(url)} doorway used by a guest, staff member, payment provider, or tour interface to begin this lodging workflow.`;
  }
  if (/->middleware\(/.test(text)) return 'Places an operational checkpoint before the workflow—such as requiring the correct signed-in user, limiting repeated requests, validating a signed link, or blocking automated spam.';
  if (/->name\(/.test(text)) return 'Gives this lodging action a stable project name so emails, buttons, redirects, notifications, and both localhost and Cloudflare deployments can link to it safely.';

  if (/\$request->validate\(|Validator::make\(/.test(text)) return `Checks the real information supplied by a guest or staff member before UHLMS attempts to ${htmlEscape(goal)}.`;
  if (/['"]required['"]|required\|/.test(text) && /=>/.test(text)) return 'Defines a piece of information the lodging operation cannot safely proceed without, preventing incomplete bookings, check-ins, payments, or staff records.';
  if (/DB::transaction\(/.test(text)) return 'Protects a multi-record lodging operation from partial completion. A failed check-in, approval, payment, or assignment cannot leave the reservation ledger and room inventory disagreeing.';
  if (/->(where|whereIn|whereHas|whereDate|whereNull|whereNotNull)\(/.test(text)) return `Selects only the reservations, rooms, guests, payments, or other records eligible for the current task: ${htmlEscape(goal)}.`;
  if (/->(with|load|loadMissing)\(/.test(text)) return 'Loads the connected lodging information together—for example a reservation with its guests, rooms, charges, and payments—so the workflow sees a complete operational picture.';
  if (/->(get|first|firstOrFail|find|findOrFail|exists|count|sum)\(/.test(text)) return `Retrieves the real records or total needed to ${htmlEscape(goal)}, such as available inventory, an owned reservation, occupancy, or a financial balance.`;
  if (/::(create|updateOrCreate|firstOrCreate)\(|->save\(\)/.test(text)) return `Commits a new piece of lodging history needed to ${htmlEscape(goal)}—for example a reservation, guest, room assignment, charge, payment, message, or audit record.`;
  if (/->update\(/.test(text)) return `Records the operational change produced while trying to ${htmlEscape(goal)}, making the new state visible to later guest and staff workflows.`;
  if (/->delete\(|::destroy\(/.test(text)) return `Removes or retires data as part of ${htmlEscape(goal)}, while the project’s policies, observers, and audit protections govern the effect on lodging history.`;
  if (/return\s+\$this->belongsToMany\(/.test(text)) return entity === 'Amenity' ? 'Models the real facilities catalogue: many room categories can offer this amenity, and each room category can advertise several amenities.' : `Models the shared real-world connection between this ${htmlEscape(humanize(entity || 'record').toLowerCase())} and several related lodging records.`;
  if (/return\s+\$this->(belongsTo|hasMany|hasOne)\(/.test(text)) return `Makes the related lodging record available through <code>${htmlEscape(context.method || 'this relationship')}()</code>, allowing other workflows to move naturally between connected reservations, rooms, guests, payments, and operational history.`;

  if (/^return\s+view\(/.test(text)) return `Shows the result of ${htmlEscape(goal)} as the appropriate guest or staff screen, using the real lodging data assembled above.`;
  if (/^return\s+(redirect|to_route)\(/.test(text)) return 'Moves the user to the correct next step after this lodging action—for example tracking, payment, account history, or the updated staff record—instead of resubmitting the same operation.';
  if (/^(if|elseif)\s*\(/.test(text)) return `Represents a lodging business decision inside the attempt to ${htmlEscape(goal)}—for example ownership, status, dates, capacity, payment state, availability, or permission.`;
  if (/^(foreach|for|while)\s*\(/.test(text)) return `Applies the current lodging rule to every relevant guest, room request, assignment, charge, payment, hotspot, or record in the collection.`;
  if (/^throw\s+|^(abort|abort_if|abort_unless)\(/.test(text)) return 'Stops the operation because continuing would expose data, violate a booking rule, use unavailable inventory, or leave the lodging records inconsistent.';
  if (/^\$this->authorize\(|Gate::/.test(text)) return 'Checks that the current staff member or guest is allowed to perform this real operation on this specific record, not merely signed in.';

  const migrationColumn = text.match(/\$table->\w+\(['"]([^'"]+)['"]/);
  if (migrationColumn) return `${fieldStory(migrationColumn[1], file)} This migration preserves that fact as part of the system’s permanent lodging records.`;
  if (/^Schema::create\(/.test(text)) return 'Creates the permanent filing area needed for this lodging concept so its operational history survives page loads, restarts, and deployments.';
  if (/^Schema::table\(/.test(text)) return 'Extends an existing lodging record structure to support a newer operational requirement without losing the data already stored.';
  if (/Schema::dropIfExists\(/.test(text)) return 'Defines how a rollback removes this filing structure when reversing the deployment, keeping database versions predictable.';

  if (/(TextInput|Textarea|Select|DatePicker|DateTimePicker|Toggle|FileUpload|Repeater|Checkbox|Radio)::make\(/.test(text)) return `Adds a staff control for capturing or changing a real lodging detail needed to ${htmlEscape(goal)}.`;
  if (/(TextColumn|IconColumn|BadgeColumn|ImageColumn)::make\(/.test(text)) return 'Shows staff an operational fact they need while monitoring inventory, bookings, guests, payments, or configuration.';
  if (/(Action|CreateAction|EditAction|DeleteAction|ViewAction|BulkAction)::make\(/.test(text)) return `Creates a staff operation used to ${htmlEscape(goal)}, with later lines controlling when it is safe and available.`;
  if (/->(hidden|visible|disabled)\(/.test(text)) return 'Prevents staff from seeing or using this control when the reservation state, permission, or business conditions make the action inappropriate.';

  if (text === '@csrf') return 'Protects a guest or staff form from being submitted by an unrelated website pretending to act as the signed-in user.';
  if (/^<form\b/i.test(text)) return 'Begins the interface through which a guest or staff member submits this real lodging action.';
  if (/^<input\b|^<select\b|^<textarea\b/i.test(text)) return `Collects one real piece of information needed by the current page’s lodging workflow.`;
  if (/\{\{.*\}\}/.test(text)) return 'Presents current lodging data—such as a room, date, price, status, guest, or message—back to the person using the page.';
  if (/\bfetch\(/.test(text)) return 'Asks the UHLMS server for fresh lodging information without reloading the whole page, commonly for tour scenes, availability, messages, or interactive reservation behavior.';
  if (/addEventListener\(/.test(text)) return 'Responds to a guest or staff interaction in the browser and starts the appropriate interface behavior for this workflow.';

  if (/^describe\(|^test\(|^it\(|function\s+test_/.test(text)) return `Defines a repeatable lodging scenario that must remain true: ${htmlEscape(goal)}.`;
  if (/->assert|\bexpect\(/.test(text)) return 'Checks the observable business result—such as access being denied, a reservation changing state, a payment being recorded, or the correct page being shown.';
  if (/factory\(\)->create|::factory\(/.test(text)) return 'Builds controlled sample lodging data so this test can reproduce the business situation reliably.';

  if (/Notification::make\(|->notify\(|Mail::/.test(text)) return 'Keeps the appropriate guest or staff member informed about a reservation, payment, offer, deadline, or support event.';
  if (/Log::(debug|info|warning|error|critical)\(/.test(text)) return 'Leaves operational evidence that helps staff or developers investigate a failed payment, webhook, backup, notification, or lodging workflow.';
  if (/Cache::|cache\(/.test(text)) return 'Reuses recently prepared operational information so common guest and staff pages respond quickly without changing the underlying lodging truth.';
  if (/env\(|config\(/.test(text)) return 'Reads an environment or administrator-controlled operating value so the same lodging system works correctly on localhost and through the Cloudflare-hosted address.';

  const categoryFallbacks = {
    'HTTP · Controllers':`This step helps the application receive a guest or staff request and ${goal}, then return a safe lodging result.`,
    'Domain · Models':`This line supports the real ${humanize(entity || 'lodging record').toLowerCase()} described above, keeping its operational facts or connections available to reservations and staff tools.`,
    'Domain · Services':`This is one step in the shared back-office procedure to ${goal}, keeping public pages and staff actions consistent.`,
    'Domain · Observers':`This supports an automatic reaction that keeps related lodging statuses, logs, notifications, or inventory synchronized after data changes.`,
    'Security · Policies':`This contributes to the authorization decision that protects real guest, booking, room, or staff data from an inappropriate operation.`,
    'Staff UI · Resources':`This configures how staff view or operate this lodging record in the administration panel.`,
    'Staff UI · Pages & Widgets':`This helps staff monitor or perform the ${goal} workflow from the administration panel.`,
    'Frontend · Blade views':`This contributes to the guest or staff screen for ${goal}, turning current lodging records into understandable information or controls.`,
    'Frontend · JavaScript':`This supports the browser interaction for ${goal}, keeping the visible interface synchronized with lodging data.`,
    'Frontend · Styles':'This presentation rule makes lodging information and controls readable, distinguishable, and usable across desktop and mobile screens.',
    'Database · Migrations':'This line supports the permanent database structure needed to preserve lodging operations and history.',
    'Database · Seed data':'This creates intentional lodging inventory or demonstration records used to initialize and understand the system.',
    'Tests · Unit & support':`This helps reproduce and verify the lodging rule that ${goal}.`,
    'Tests · Feature':`This helps simulate a real guest or staff journey and prove that ${goal}.`,
    'Configuration':'This operating value controls how a lodging subsystem behaves across local and hosted environments.',
    'Communication':'This supports a guest or staff message tied to a reservation, payment, offer, or support workflow.',
    'Operations · Jobs':'This is part of background lodging work that must continue reliably outside the immediate browser request.',
    'Operations · Commands':'This supports scheduled or administrator-triggered maintenance of reservation and notification state.',
    'Operations · Scripts':'This helps start, deploy, expose, or stop the lodging application consistently in its supported environments.',
  };
  return categoryFallbacks[file.category] || `This line contributes to the real project responsibility described above: ${htmlEscape(realWorldStory(file))}`;
}

function completeExplanation(line, file, number, lines, contexts) {
  const operational = operationalExplanation(line, file, number, lines, contexts);
  if (!operational) return '';
  const mechanics = explainLine(line, file, number, lines, contexts);
  return `<div class="operational-meaning"><strong>Operational meaning</strong><span>${operational}</span></div>${mechanics ? `<details class="code-mechanics"><summary>Code mechanics</summary><div>${mechanics}</div></details>` : ''}`;
}

function explainLine(line, file, number, lines, contexts) {
  const text = line.trim();
  const previous = (lines[number - 2] || '').trim();
  const next = (lines[number] || '').trim();
  const type = file.language;
  const context = contexts[number - 1] || { method:null, goal:file.purpose };
  const goal = context.goal.replace(/\.$/, '');
  const inMethod = context.method ? `Inside <code>${htmlEscape(context.method)}()</code>, this helps ${htmlEscape(goal)}.` : `At file level, this supports the file’s role: ${htmlEscape(file.purpose)}`;
  if (!text) return '';
  if (/^[{}()[\],;]+$/.test(text)) return '';
  const fieldMatch = text.match(/^['"]([^'"]+)['"]\s*(?:=>\s*['"]([^'"]+)['"])?[,]?$/);
  if (fieldMatch && context.section === 'fillable') return `${htmlEscape(fieldStory(fieldMatch[1], file))} Listing it here allows the application’s approved forms and workflows to save that value on this record.`;
  if (fieldMatch && context.section === 'casts') return `${htmlEscape(fieldStory(fieldMatch[1], file))} Converting it to <code>${htmlEscape(fieldMatch[2] || 'the declared type')}</code> keeps staff filters, guest visibility checks, and business decisions consistent instead of relying on raw database text or numbers.`;
  if (/^protected\s+\$fillable\s*=/.test(text)) return `Lists the real-world details that approved UHLMS forms may save for this ${htmlEscape(humanize(entityNameFor(file) || 'record').toLowerCase())}. This boundary prevents unrelated request fields from silently changing lodging data.`;
  if (/^return\s*\[$/.test(text) && context.method === 'casts') return `Returns the storage-to-business conversions for this record so the rest of UHLMS receives dependable values when making lodging decisions.`;
  if (text.startsWith('#!/usr/bin/env php')) return 'Lets Unix-like systems launch this project’s <code>artisan</code> file with the available PHP interpreter when a developer or server runs Laravel commands.';
  if (/define\(['"]LARAVEL_START['"]/.test(text)) return 'Records when this Laravel command began so framework diagnostics can measure the full startup and execution time of UHLMS maintenance commands.';
  if (/require\s+__DIR__.*vendor\/autoload\.php/.test(text)) return 'Loads Composer’s class map. Without it, <code>artisan</code> could not locate Laravel, Filament, PayMongo helpers, or any class under the project’s <code>App\\</code> namespace.';
  if (/require_once\s+__DIR__.*bootstrap\/app\.php/.test(text)) return 'Builds the configured UHLMS Laravel application from <code>bootstrap/app.php</code>, including its web/console routes, middleware, providers, policies, and exception handling.';
  if (/->handleCommand\(/.test(text)) return 'Hands the terminal arguments to the booted Laravel application. Laravel resolves the matching project command, runs it, and returns a process status code.';
  if (/exit\(\$status\)/.test(text)) return 'Ends the command-line process with Laravel’s result code so batch files, schedulers, deployment scripts, and CI can tell whether the UHLMS command succeeded.';
  if (/^(\/\/|#(?!\[)|\/\*|\*|<!--)/.test(text)) return `This project note explains the intent of the surrounding block: “${htmlEscape(normalize(text.replace(/^(\/\/|#|\/\*+|\*+|<!--|-->)/, '')))}”. ${inMethod}`;
  if (text === '<?php') return 'Starts PHP mode. The server interprets all following PHP statements instead of sending them directly to the browser.';
  if (/^namespace\s+/.test(text)) return `Places this class in the <code>${htmlEscape(text.replace(/^namespace\s+|;$/g, ''))}</code> namespace, preventing name collisions and matching Composer autoloading.`;
  if (/^use\s+[^({]+;$/.test(text)) {
    const imported = text.replace(/^use\s+|;$/g, '');
    const shortName = imported.split('\\').pop();
    return `Makes <code>${htmlEscape(shortName)}</code> available because this file needs it to ${htmlEscape(dependencyReason(shortName, file))}. This connects the current file to <code>${htmlEscape(imported)}</code>.`;
  }
  if (/^use\s+\(/.test(text)) return 'Captures the listed outside variables inside this anonymous function. Without this clause, the callback could not read them.';
  if (/^(final\s+|abstract\s+)?class\s+/.test(text)) {
    const match = text.match(/class\s+(\w+)(?:\s+extends\s+([^\s{]+))?(?:\s+implements\s+(.+?))?\s*\{/);
    if (match) return `Defines the UHLMS concept <code>${match[1]}</code>. In real lodging operations: ${htmlEscape(realWorldStory(file))}${match[2] ? ` Extending <code>${htmlEscape(match[2])}</code> lets the system store, retrieve, and relate this operational record through Laravel.` : ''}`;
  }
  if (/^(interface|trait|enum)\s+/.test(text)) return `Declares a PHP ${text.split(/\s/)[0]} and opens its definition.`;
  if (/^(public|protected|private)\s+(static\s+)?function\s+|^function\s+/.test(text)) {
    const name = text.match(/function\s+(\w+)/)?.[1] || 'anonymous function';
    const visibility = text.match(/^(public|protected|private)/)?.[1] || 'local';
    return `Starts <code>${name}()</code>. In this project, its job is to ${htmlEscape(describeMethod(name, file))}. Its ${visibility} visibility shows whether framework/calling code or only this class may invoke it.`;
  }
  if (/^(public|protected|private)\s+(static\s+)?[?$\w\\|]+\s+\$\w+/.test(text)) return 'Declares a class property. Its visibility controls access, its type constrains valid values, and any value after <code>=</code> is the default.';
  if (/^protected\s+\$fillable\s*=/.test(text)) return `Identifies which real lodging details may be saved by approved UHLMS forms for this record, preventing unexpected request fields from altering operational data.`;
  if (/^protected\s+\$casts\s*=|function\s+casts\s*\(/.test(text)) return 'Defines Eloquent type conversions so raw database values become dependable PHP booleans, dates, arrays, decimals, or enums.';
  if (/return\s+\$this->belongsTo\(/.test(text)) return `Connects this ${htmlEscape(file.path.split('/').pop().replace('.php',''))} record to its parent through <code>${htmlEscape(context.method || 'this relationship')}</code>. UHLMS uses that link so ${htmlEscape(goal)} without manually joining database tables.`;
  if (/return\s+\$this->hasMany\(/.test(text)) return `Exposes the child records named by <code>${htmlEscape(context.method || 'this relationship')}</code>. Reservation, room, ledger, and guest workflows can now traverse this relationship from the current model.`;
  if (/return\s+\$this->hasOne\(/.test(text)) return 'Declares a one-to-one Eloquent relationship and returns its query definition.';
  if (/return\s+\$this->belongsToMany\(/.test(text)) {
    const related = text.match(/belongsToMany\((\w+)::class/)?.[1] || 'related records';
    if (entityNameFor(file) === 'Amenity' && related === 'RoomType') return 'Connects this facility to every accommodation category that offers it. For example, “Wi-Fi” may belong to Standard, Deluxe, and Dormitory room types, while each room type can list several amenities. The pivot table stores those catalogue pairings without duplicating either record.';
    return `Connects this ${htmlEscape(humanize(entityNameFor(file) || 'record').toLowerCase())} to multiple ${htmlEscape(humanize(related).toLowerCase())} records and allows the reverse connection too. UHLMS uses the shared table to model a real many-to-many lodging relationship without duplicating master records.`;
  }
  if (/Route::(get|post|put|patch|delete|any|match)/.test(text)) {
    const verb = text.match(/Route::(\w+)/)?.[1].toUpperCase();
    const url = text.match(/\(['"]([^'"]+)/)?.[1];
    const handler = text.match(/\[([\w]+)::class,\s*['"]([^'"]+)/);
    return `Creates the project entry point <code>${verb} ${htmlEscape(url || '')}</code>${handler ? ` and hands it to <code>${handler[1]}::${handler[2]}()</code>` : ''}. This is how a browser or integration reaches the related UHLMS workflow; later middleware and route naming secure and identify it.`;
  }
  if (/Route::(prefix|middleware|name)\(/.test(text)) return 'Starts a route group that applies the shown URL prefix, middleware checkpoints, or naming prefix to every nested route.';
  if (/->middleware\(/.test(text)) return 'Attaches request checkpoints such as authentication, throttling, signatures, CSRF/session handling, or spam protection before the endpoint runs.';
  if (/->name\(/.test(text)) return 'Assigns a stable route name so the application can generate this URL without hard-coding a hostname or path.';
  if (/\$request->validate\(|Validator::make\(/.test(text)) return `Checks browser or integration input before <code>${htmlEscape(context.method || 'this workflow')}()</code> uses it. This protects ${htmlEscape(goal)} from incomplete, malformed, or manipulated data.`;
  if (/['"]required['"]|required\|/.test(text) && /=>/.test(text)) return 'Declares validation rules for this input. <code>required</code> prevents the field from being omitted; the remaining rules constrain its type, format, range, or relationship.';
  if (/DB::transaction\(/.test(text)) return `Makes the multi-record work in <code>${htmlEscape(context.method || 'this workflow')}()</code> atomic. UHLMS must not leave reservations, rooms, guests, charges, or payments half-updated if a later step fails.`;
  if (/->(where|whereIn|whereHas|whereDate|whereNull|whereNotNull)\(/.test(text)) return `Narrows the records to the business-relevant subset needed to ${htmlEscape(goal)}. This prevents the workflow from acting on unrelated reservations, rooms, users, or ledger entries.`;
  if (/->(orderBy|latest|oldest|sortBy|groupBy)\(/.test(text)) return 'Controls the ordering or grouping of the records so later display and processing receive a predictable sequence.';
  if (/->(select|addSelect|pluck|value)\(/.test(text)) return 'Chooses the database fields or single value that should be returned instead of loading unnecessary record data.';
  if (/->(with|load|loadMissing)\(/.test(text)) return 'Eager-loads related records to make them available together and avoid repeated database queries later.';
  if (/->(get|first|firstOrFail|find|findOrFail|exists|count|sum)\(/.test(text)) return 'Executes or resolves the database query and returns the requested collection, record, aggregate, or existence result.';
  if (/::(create|updateOrCreate|firstOrCreate)\(/.test(text)) return 'Persists a new record, or finds/updates one according to the selected Eloquent operation, using the supplied attributes.';
  if (/->update\(/.test(text)) return 'Writes the supplied field changes to the selected record or records and updates their modification timestamp.';
  if (/->delete\(|::destroy\(/.test(text)) return 'Deletes the selected database record(s), invoking model events and observers when Eloquent performs the operation.';
  if (/->save\(\)/.test(text)) return 'Persists the current in-memory model values to the database, choosing an insert or update as appropriate.';
  if (/^return\s+view\(/.test(text)) {
    const viewName = text.match(/view\(['"]([^'"]+)/)?.[1];
    return `Finishes <code>${htmlEscape(context.method || 'this controller action')}()</code> by rendering ${viewName ? `<code>resources/views/${htmlEscape(viewName.replaceAll('.', '/'))}.blade.php</code>` : 'its Blade page'}. The data assembled above becomes the guest or staff interface shown in the browser.`;
  }
  if (/^return\s+(redirect|to_route)\(/.test(text)) return 'Returns an HTTP redirect so the browser makes a new request to the chosen destination.';
  if (/^return\s+response\(\)->json|^return\s+response\(\)->/.test(text)) return 'Builds and returns an explicit HTTP response, including its body, status, or headers.';
  if (/route\(|url\(|asset\(|storage_path\(|public_path\(|base_path\(/.test(text)) return 'Generates a route-aware URL or an application filesystem path through Laravel instead of hard-coding an environment-specific location.';
  if (/^return\s+/.test(text)) return 'Ends the current function and sends this value back to its caller.';
  if (/^(if|elseif)\s*\(/.test(text)) return `Protects the ${htmlEscape(context.method || 'current')} workflow with a business decision. Only records or requests satisfying this condition may continue down the following branch, helping ${htmlEscape(goal)} safely.`;
  if (/^else\s*\{?/.test(text)) return 'Provides the fallback branch when the preceding <code>if</code> or <code>elseif</code> condition was false.';
  if (/^(foreach|for|while)\s*\(/.test(text)) return 'Starts a loop. The following block repeats for each selected item or while the stated condition remains true.';
  if (/^try\s*\{/.test(text)) return 'Begins protected work that may throw an exception; a following catch/finally block defines failure or cleanup behavior.';
  if (/^}\s*catch\s*\(/.test(text) || /^catch\s*\(/.test(text)) return 'Catches the specified exception so the application can log, translate, recover from, or report the failure deliberately.';
  if (/^throw\s+/.test(text)) return 'Stops normal execution by raising an exception. An enclosing handler may catch it; otherwise Laravel converts it into an error response or failed job.';
  if (/^(abort|abort_if|abort_unless)\(/.test(text)) return 'Stops the request with an HTTP error when the stated access or validity condition requires it.';
  if (/^\$this->authorize\(|Gate::/.test(text)) return 'Performs server-side authorization before allowing the requested action on this resource.';
  if (/^config\(|env\(/.test(text) || /=>\s*env\(/.test(text)) return 'Reads environment-aware configuration. Runtime-specific values belong outside source code so localhost and hosted deployments can differ safely.';
  if (/^Schema::create\(/.test(text)) return 'Creates the named database table; the callback describes its columns, indexes, and constraints.';
  if (/^Schema::table\(/.test(text)) return 'Alters an existing database table through the enclosed schema instructions.';
  if (/Schema::dropIfExists\(/.test(text)) return 'Removes the table when reversing or retiring this schema change, without failing if it is already absent.';
  if (/\$table->id\(\)/.test(text)) return 'Adds the auto-incrementing primary key named <code>id</code>.';
  if (/\$table->timestamps\(\)/.test(text)) return 'Adds Laravel’s <code>created_at</code> and <code>updated_at</code> audit timestamps.';
  if (/\$table->foreignId\(/.test(text)) return 'Adds a foreign-key identifier connecting this table to another record; chained calls define the referenced table and deletion behavior.';
  if (/\$table->(string|text|integer|unsignedInteger|decimal|boolean|date|dateTime|timestamp|json|enum)\(/.test(text)) return 'Adds the described typed database column. Chained modifiers control nullability, defaults, indexes, uniqueness, or precision.';
  if (/^@extends\(/.test(text)) return 'Makes this Blade view inherit the named layout so shared page structure does not need to be repeated.';
  if (/^@(section|yield|include|component)\b/.test(text)) return 'Uses a Blade composition directive to define, place, or reuse a named portion of the rendered interface.';
  if (/^@(if|elseif|unless|auth|guest|can)\b/.test(text)) return 'Begins or continues a Blade condition; the enclosed markup is rendered only when its server-side rule allows it.';
  if (/^@(foreach|forelse|for|while)\b/.test(text)) return 'Begins a Blade loop that repeats the enclosed markup for records or while a condition holds.';
  if (/^@(end|else|empty)/.test(text)) return 'Closes or changes the current Blade control-flow block.';
  if (text === '@csrf') return 'Prints a hidden CSRF token field. Laravel uses it to reject forged state-changing form submissions.';
  if (/\{\{.*\}\}/.test(text)) return 'Renders server-provided data through Blade’s escaped output syntax, protecting HTML special characters by default.';
  if (/^<form\b/i.test(text)) return 'Starts an HTML form. Its method and action determine which server endpoint receives the user’s submitted fields.';
  if (/^<input\b|^<select\b|^<textarea\b/i.test(text)) return 'Defines an interactive form control. Its name becomes the request-data key sent to the server.';
  if (/^<\/?[a-z][^>]*>/i.test(text)) return 'Defines part of the rendered HTML structure, content, accessibility metadata, or interface control.';
  if (/\bfetch\(/.test(text)) return 'Starts an asynchronous HTTP request from the browser. Later code checks the response and parses or displays its data.';
  if (/JSON\.(parse|stringify)\(/.test(text)) return 'Converts between a JavaScript value and JSON text so structured data can be stored, sent, or received.';
  if (/localStorage\.(getItem|setItem|removeItem)\(/.test(text)) return 'Reads, writes, or removes a browser-local preference that remains available on this device between page loads.';
  if (/addEventListener\(/.test(text)) return 'Registers a browser event handler so this callback runs when the specified user or system event occurs.';
  if (/document\.(querySelector|getElementById|querySelectorAll)/.test(text)) return 'Finds one or more elements in the current page so JavaScript can read or update the interface.';
  if (/await\s+/.test(text)) return 'Pauses this async function until the promise settles, allowing the following line to use its completed result.';
  if (/^(const|let|var)\s+/.test(text)) return `Declares a JavaScript variable. <code>${text.startsWith('const') ? 'const' : text.startsWith('let') ? 'let' : 'var'}</code> controls whether the binding may be reassigned and its scope.`;
  if (/^function\s+|^(?:export\s+)?(?:async\s+)?function\s+/.test(text)) return 'Begins a named JavaScript function; its parameters are inputs and its block contains reusable behavior.';
  if (/=>\s*\{?$/.test(text)) return 'Defines an arrow-function callback, commonly passed to an event, collection operation, promise, or framework API.';
  if (/^describe\(|^test\(|^it\(|function\s+test_/.test(text)) return 'Begins an automated test whose name states the behavior this block must prove.';
  if (/->assert|\bexpect\(/.test(text)) return 'Asserts the expected result. The test fails here if actual application behavior does not match this contract.';
  if (/\$this->(get|post|put|patch|delete)\(/.test(text)) return 'Makes a simulated HTTP request inside the feature test so the full Laravel request path can be verified.';
  if (/factory\(\)->create|::factory\(/.test(text)) return 'Creates controlled test data through a factory so this scenario starts from a known database state.';
  if (/(TextInput|Textarea|Select|DatePicker|DateTimePicker|Toggle|FileUpload|Repeater|Checkbox|Radio)::make\(/.test(text)) return 'Creates a Filament form field bound to the named model attribute or temporary form-state key.';
  if (/(TextColumn|IconColumn|BadgeColumn|ImageColumn)::make\(/.test(text)) return 'Creates a Filament table column that reads the named record attribute or relationship value.';
  if (/(Action|CreateAction|EditAction|DeleteAction|ViewAction|BulkAction)::make\(/.test(text)) return 'Defines a Filament user action; the following chained calls configure its label, visibility, confirmation, form, and callback.';
  if (/(Section|Grid|Fieldset|Tabs|Group)::make\(/.test(text)) return 'Starts a Filament layout component that groups related fields or information in the staff interface.';
  if (/->(label|helperText|placeholder|hint|description)\(/.test(text)) return 'Sets human-readable interface text for the current field, column, action, statistic, or component.';
  if (/->(required|nullable|disabled|hidden|visible|searchable|sortable|unique|multiple|preload)\(/.test(text)) return 'Adds the named validation, interaction, visibility, or table behavior to the component being configured.';
  if (/->(options|schema|columns|actions|filters|bulkActions|headerActions)\(/.test(text)) return 'Supplies the nested choices, components, columns, filters, or actions used by the current Filament builder.';
  if (/Notification::make\(|->notify\(|Mail::/.test(text)) return 'Begins or sends an application notification/email so the appropriate staff member or guest is informed of this event.';
  if (/Log::(debug|info|warning|error|critical)\(/.test(text)) return 'Writes structured diagnostic information to Laravel’s logs at the selected severity level.';
  if (/Cache::|cache\(/.test(text)) return 'Reads or updates cached data to avoid repeating more expensive database or computation work.';
  if (type === 'CSS' && /\{$/.test(text)) return `Starts the CSS rule for <code>${htmlEscape(text.slice(0, -1).trim())}</code>; enclosed declarations apply to matching elements.`;
  if (type === 'CSS' && /^[\w-]+\s*:/.test(text)) return 'Sets one visual property for the current CSS selector. The value after the colon controls its presentation.';
  if (type === 'JSON' && /^"[^"]+"\s*:/.test(text)) return 'Defines a named JSON configuration field and assigns its value, nested object, or list.';
  if (/^(public|protected|private)\s+const\s+|^const\s+/.test(text)) return 'Declares a constant whose value is intended to remain unchanged and be reused by name.';
  if (/^\$[\w]+\s*=/.test(text)) {
    const variable = text.match(/^\$(\w+)/)?.[1];
    return `Builds the <code>$${htmlEscape(variable)}</code> value for later steps in <code>${htmlEscape(context.method || 'this file')}()</code>. It keeps the intermediate result available while the method works to ${htmlEscape(goal)}.`;
  }
  if (/^\[[^\]]*\]$|^\];?$/.test(text)) return text.startsWith('[') ? 'Creates an array value from the listed elements.' : 'Closes the current array declaration.';
  if (/^['"][^'"]+['"]\s*=>/.test(text)) return 'Maps this named array key to the value or nested configuration on the right.';
  if (/^}\)?[,;]?$/.test(text)) return '';
  if (/^\);?$/.test(text)) return '';
  if (/^\},?$/.test(text)) return '';
  if (/^[{}()[\],;]+$/.test(text)) return '';
  if (next.startsWith('->')) return 'Begins a value or builder expression that the following chained method calls continue configuring.';
  return `${inMethod} This statement supplies, transforms, or passes a value needed by the surrounding block; its project meaning comes from the ${htmlEscape(context.method || file.path.split('/').pop())} workflow rather than from the ${htmlEscape(type)} syntax alone.`;
}

function updateViewButtons() {
  document.querySelector('#referenceViewButton').classList.toggle('active', view === 'reference');
  document.querySelector('#lessonViewButton').classList.toggle('active', view === 'lessons');
  searchInput.placeholder = view === 'reference' ? 'Search files, symbols, or code…' : 'Search lessons…';
}

function renderReferenceNav() {
  const groups = new Map();
  const activeCategory = sourceFiles[currentIndex]?.category;
  sourceFiles.forEach((file, index) => {
    if (!groups.has(file.category)) groups.set(file.category, []);
    groups.get(file.category).push({ file, index });
  });
  document.querySelector('.progress-label span').textContent = 'Files covered';
  document.querySelector('#progressText').textContent = `${sourceFiles.length} files`;
  document.querySelector('#progressBar').style.width = '100%';
  nav.innerHTML = `<div class="sidebar-view-links"><a class="active" href="#file=${encodeURIComponent(sourceFiles[referenceStartIndex].path)}">File-by-file guide</a><a href="#welcome">Guided lessons</a></div>${[...groups.entries()].map(([category, items]) => `<details class="file-group" ${category === activeCategory ? 'open' : ''}><summary><span>${htmlEscape(category)}</span><small>${items.length}</small></summary><div class="file-group-links">${items.map(({file,index}) => `<a class="chapter-link file-link" data-file-index="${index}" href="#file=${encodeURIComponent(file.path)}" title="${htmlEscape(file.path)}">${htmlEscape(file.path.split('/').pop())}</a>`).join('')}</div></details>`).join('')}`;
}

function renderLessonNav() {
  let section = '';
  document.querySelector('.progress-label span').textContent = 'Lesson progress';
  nav.innerHTML = `<div class="sidebar-view-links"><a href="#file=${encodeURIComponent(sourceFiles[referenceStartIndex].path)}">File-by-file guide</a><a class="active" href="#welcome">Guided lessons</a></div>${chapters.map((chapter, index) => {
    const heading = chapter.section !== section ? `<div class="nav-heading">${section = chapter.section}</div>` : '';
    return `${heading}<a class="chapter-link ${completed.has(chapter.id) ? 'done' : ''}" data-index="${index}" href="#${chapter.id}">${chapter.shortTitle}</a>`;
  }).join('')}`;
  updateProgress();
}

function renderNav() {
  view === 'reference' ? renderReferenceNav() : renderLessonNav();
  updateViewButtons();
}

function renderChapter(index) {
  currentIndex = Math.max(0, Math.min(chapters.length - 1, index));
  const chapter = chapters[currentIndex];
  content.innerHTML = `<div class="lesson"><p class="eyebrow">${chapter.section} · Chapter ${currentIndex + 1} of ${chapters.length}</p><h1>${chapter.title}</h1><p class="lead">${chapter.summary}</p><div class="lesson-meta"><span class="pill">${chapter.time}</span><span class="pill">${chapter.level}</span></div>${chapter.html}</div>`;
  document.querySelectorAll('.chapter-link').forEach((link, i) => {
    const active = i === currentIndex;
    link.classList.toggle('active', active);
    active ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current');
  });
  document.querySelector('#previousButton').disabled = currentIndex === 0;
  document.querySelector('#nextButton').disabled = currentIndex === chapters.length - 1;
  document.querySelector('#completeButton').hidden = false;
  document.querySelector('#completeButton').textContent = completed.has(chapter.id) ? 'Completed ✓' : 'Mark complete & continue ✓';
  document.title = `${chapter.shortTitle} — UHLMS Code Reference`;
  bindCopyButtons();
  updateProgress();
  window.scrollTo(0, 0);
}

function renderFile(index) {
  currentIndex = Math.max(0, Math.min(sourceFiles.length - 1, index));
  const file = sourceFiles[currentIndex];
  const sourceLines = file.source.split('\n');
  const className = file.path.split('/').pop().replace(/\.blade\.php$|\.[^.]+$/g, '');
  const methods = file.symbols.filter(symbol => symbol.name !== className);
  const flow = fileFlow(file);
  const methodCards = methods.length ? `<section class="file-lesson-section"><h2>Important functions in this file</h2><p class="section-intro">Read the function names as the jobs this file can perform.</p><div class="method-card-grid">${methods.map(symbol => `<button type="button" class="method-card" data-jump-line="${symbol.line}"><span><code>${htmlEscape(symbol.name)}()</code><small>Starts at source line ${symbol.line}</small></span><p>${htmlEscape(describeMethod(symbol.name, file))}.</p></button>`).join('')}</div></section>` : '';
  const connectionList = items => items.slice(0, 20).map(path => `<a href="#file=${encodeURIComponent(path)}">${htmlEscape(path)}</a>`).join('') + (items.length > 20 ? `<span class="connection-more">+${items.length - 20} more</span>` : '');
  const hasConnections = file.uses?.length || file.referencedBy?.length;
  const connections = hasConnections ? `<details class="file-connections"><summary>Where this file connects to the project</summary><div class="project-connections">${file.uses?.length ? `<div><strong>Files it uses</strong><p>These provide models, services, views, or helpers needed by this file.</p><div class="connection-links">${connectionList(file.uses)}</div></div>` : ''}${file.referencedBy?.length ? `<div><strong>Files that use it</strong><p>These depend on this file’s responsibility.</p><div class="connection-links">${connectionList(file.referencedBy)}</div></div>` : ''}</div></details>` : '';
  const sourceCode = sourceLines.map((line, lineIndex) => `<span id="source-line-${lineIndex + 1}" class="source-only-line"><a href="#file=${encodeURIComponent(file.path)}&line=${lineIndex + 1}" aria-label="Link to source line ${lineIndex + 1}">${lineIndex + 1}</a><code>${htmlEscape(line) || '&nbsp;'}</code></span>`).join('');
  content.innerHTML = `<article class="reference-file file-lesson"><header class="reference-header"><div class="reference-breadcrumb"><span>${htmlEscape(file.category)}</span><span>File ${currentIndex + 1} of ${sourceFiles.length}</span></div><h1>${htmlEscape(file.path)}</h1><p class="lead">${file.purpose}</p><div class="lesson-meta"><span class="pill">${file.language}</span><span class="pill">${file.lines.toLocaleString()} source lines</span><span class="pill">File-by-file lesson</span></div></header><aside class="file-study-guide"><strong>Learn the file first</strong><span>Understand its responsibility and workflow below. Open the source only when you are ready to connect the explanation to the implementation.</span></aside><section class="file-overview-grid"><div class="file-overview-card why-card"><span class="card-number">1</span><h2>Why does this file exist?</h2><p>${htmlEscape(fileWhyItExists(file))}</p></div><div class="file-overview-card role-card"><span class="card-number">2</span><h2>What does it represent in UHLMS?</h2><p>${htmlEscape(realWorldStory(file))}</p></div></section><section class="file-lesson-section how-section"><h2>How does it function?</h2><p>${fileHowItWorks(file)}</p></section><section class="file-lesson-section"><h2>How information moves through it</h2><div class="file-flow"><div><span>1</span><strong>Information entering</strong><p>${htmlEscape(flow[0])}</p></div><div><span>2</span><strong>Work performed</strong><p>${htmlEscape(flow[1])}</p></div><div><span>3</span><strong>Result produced</strong><p>${htmlEscape(flow[2])}</p></div></div></section>${methodCards}${connections}<details class="full-source-details"><summary><span><strong>View the complete source code</strong><small>Optional reference · ${file.lines.toLocaleString()} lines</small></span><span class="details-action">Expand</span></summary><div class="source-only-toolbar"><span>${htmlEscape(file.path)}</span><button id="copyWholeFile" type="button">Copy file</button></div><pre class="source-only-code"><code>${sourceCode}</code></pre></details></article>`;
  document.querySelectorAll('.file-link').forEach(link => {
    const active = Number(link.dataset.fileIndex) === currentIndex;
    link.classList.toggle('active', active);
    active ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current');
  });
  document.querySelector('#previousButton').disabled = currentIndex === 0;
  document.querySelector('#nextButton').disabled = currentIndex === sourceFiles.length - 1;
  document.querySelector('#completeButton').hidden = true;
  document.querySelector('#copyWholeFile').addEventListener('click', async event => {
    await navigator.clipboard.writeText(file.source);
    event.currentTarget.textContent = 'Copied!';
    setTimeout(() => event.currentTarget.textContent = 'Copy file', 1200);
  });
  document.querySelectorAll('[data-jump-line]').forEach(button => button.addEventListener('click', () => {
    const details = document.querySelector('.full-source-details');
    details.open = true;
    setTimeout(() => document.querySelector(`#source-line-${button.dataset.jumpLine}`)?.scrollIntoView({ behavior:'smooth', block:'center' }), 0);
  }));
  document.title = `${file.path} — UHLMS File Guide`;
  const requestedLine = Number(new URLSearchParams(location.hash.slice(1).replace(/^file=[^&]*/, '')).get('line'));
  if (requestedLine) {
    document.querySelector('.full-source-details').open = true;
    setTimeout(() => document.querySelector(`#source-line-${requestedLine}`)?.scrollIntoView({ block:'center' }), 0);
  }
  else window.scrollTo(0, 0);
}

function bindCopyButtons() {
  document.querySelectorAll('.code-wrap').forEach(block => {
    const button = block.querySelector('.copy-code');
    if (!button) return;
    button.addEventListener('click', async () => {
      await navigator.clipboard.writeText(decodeURIComponent(block.querySelector('code').dataset.source));
      button.textContent = 'Copied!';
      setTimeout(() => button.textContent = 'Copy', 1200);
    });
  });
}

function updateProgress() {
  if (view !== 'lessons') return;
  const percent = Math.round(completed.size / chapters.length * 100);
  document.querySelector('#progressText').textContent = `${percent}%`;
  document.querySelector('#progressBar').style.width = `${percent}%`;
}

function route() {
  if (location.hash.startsWith('#file=')) {
    view = 'reference';
    const raw = location.hash.slice(6).split('&')[0];
    const filePath = decodeURIComponent(raw);
    const index = sourceFiles.findIndex(file => file.path === filePath);
    currentIndex = index < 0 ? 0 : index;
    renderNav();
    renderFile(currentIndex);
  } else {
    view = 'lessons';
    const id = location.hash.slice(1) || chapters[0].id;
    const index = chapters.findIndex(chapter => chapter.id === id);
    renderNav();
    renderChapter(index < 0 ? 0 : index);
  }
  localStorage.setItem('uhlms-tutorial-view', view);
  document.querySelector('#sidebar').classList.remove('open');
  document.querySelector('#scrim').classList.remove('open');
}

let searchTimer;
searchInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    const query = searchInput.value.trim().toLowerCase();
    if (query.length < 2) { searchResults.hidden = true; return; }
    if (view === 'reference') {
      const matches = sourceFiles.filter(file => `${file.path} ${file.purpose} ${file.symbols.map(item => item.name).join(' ')} ${file.source}`.toLowerCase().includes(query)).slice(0, 40);
      searchResults.innerHTML = matches.length ? matches.map(file => `<a class="search-result" href="#file=${encodeURIComponent(file.path)}"><strong>${htmlEscape(file.path)}</strong><br><small>${file.purpose}</small></a>`).join('') : '<span>No source files found.</span>';
    } else {
      const matches = chapters.filter(chapter => `${chapter.title} ${chapter.summary} ${chapter.search}`.toLowerCase().includes(query));
      searchResults.innerHTML = matches.length ? matches.map(chapter => `<a class="search-result" href="#${chapter.id}"><strong>${chapter.title}</strong><br><small>${chapter.summary}</small></a>`).join('') : '<span>No lessons found.</span>';
    }
    searchResults.hidden = false;
  }, 180);
});
searchResults.addEventListener('click', () => { searchResults.hidden = true; searchInput.value = ''; });
nav.addEventListener('click', event => {
  const link = event.target.closest('a');
  if (!link) return;
  event.preventDefault();
  location.hash = link.getAttribute('href').slice(1);
});
document.querySelector('#previousButton').addEventListener('click', () => location.hash = view === 'reference' ? `file=${encodeURIComponent(sourceFiles[currentIndex - 1].path)}` : chapters[currentIndex - 1].id);
document.querySelector('#nextButton').addEventListener('click', () => location.hash = view === 'reference' ? `file=${encodeURIComponent(sourceFiles[currentIndex + 1].path)}` : chapters[currentIndex + 1].id);
document.querySelector('#completeButton').addEventListener('click', () => {
  const id = chapters[currentIndex].id;
  completed.add(id);
  localStorage.setItem('uhlms-tutorial-progress', JSON.stringify([...completed]));
  if (currentIndex < chapters.length - 1) location.hash = chapters[currentIndex + 1].id;
  else { renderNav(); renderChapter(currentIndex); }
});
document.querySelector('#referenceViewButton').addEventListener('click', () => location.hash = `file=${encodeURIComponent(sourceFiles[referenceStartIndex].path)}`);
document.querySelector('#lessonViewButton').addEventListener('click', () => location.hash = chapters[Math.min(currentIndex, chapters.length - 1)].id);
document.querySelector('#themeButton').addEventListener('click', () => {
  document.body.classList.toggle('dark');
  localStorage.setItem('uhlms-tutorial-theme', document.body.classList.contains('dark') ? 'dark' : 'light');
});
document.querySelector('#menuButton').addEventListener('click', () => {
  const open = document.querySelector('#sidebar').classList.toggle('open');
  document.querySelector('#scrim').classList.toggle('open', open);
  document.querySelector('#menuButton').setAttribute('aria-expanded', open);
});
document.querySelector('#scrim').addEventListener('click', () => document.querySelector('#menuButton').click());
if (localStorage.getItem('uhlms-tutorial-theme') === 'dark') document.body.classList.add('dark');
window.addEventListener('hashchange', route);
if (!location.hash) location.hash = `file=${encodeURIComponent(sourceFiles[referenceStartIndex]?.path || '')}`;
else route();
