const esc = value => value.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
const code = (title, value) => {
  const source = value.trim();
  const numberedLines = source.split('\n').map((line, index) => `<span class="code-line"><span class="line-number" aria-hidden="true">${index + 1}</span><span class="code-text">${esc(line) || '&nbsp;'}</span></span>`).join('');
  return `<div class="code-wrap"><div class="code-title"><span>${title}</span><button class="copy-code" type="button">Copy</button></div><div class="code-scroll"><pre><code class="code-lines" data-source="${encodeURIComponent(source)}">${numberedLines}</code></pre></div></div>`;
};
const lines = rows => `<table class="line-table"><thead><tr><th>Line(s)</th><th>What the code means</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${r[0]}</td><td>${r[1]}</td></tr>`).join('')}</tbody></table>`;
const callout = (kind, title, body) => `<aside class="callout ${kind}"><strong>${title}</strong>${body}</aside>`;
const files = rows => `<div class="file-grid">${rows.map(r=>`<div class="file-card"><code>${r[0]}</code><span>${r[1]}</span></div>`).join('')}</div>`;

window.UHLMS_TUTORIAL = [
{
 id:'welcome', section:'Start here', shortTitle:'Welcome', title:'Learn Your Project From the Inside Out', time:'10 min', level:'Beginner',
 summary:'This tutorial turns the UH Lodging Management System into a guided learning path—from the first browser request to reservations, payments, check-in, admin screens, and virtual tours.',
 search:'learning path first party vendor artisan php laravel architecture',
 html:`${callout('analogy','The hotel analogy','Think of the application as a real hotel. Routes are the front desk directory, controllers are receptionists, services are specialist staff, models are record cards, the database is the filing room, Blade views are the rooms guests see, and policies are the security guards.')}
 <h2>What you are learning</h2><p>UHLMS is a Laravel 11 application with two faces: a public guest website and a Filament staff panel. It manages rooms, reservations, guest accounts, payments, check-in/out, support inquiries, feedback, reports, and 360° tours.</p>
 <h2>How to use this course</h2><ol><li>Read in order the first time.</li><li>Open the named source file beside the lesson.</li><li>Type small examples yourself instead of only copying.</li><li>Use the check mark below to track progress in this browser.</li></ol>
 ${callout('note','About “line by line”','Important or unfamiliar code is reproduced and annotated line by line. Repeated Filament page shells and generated framework assets are explained as patterns rather than repeated hundreds of times. The File Atlas accounts for every first-party area and tells you what to inspect next.')}
 <h2>The big picture</h2><div class="flow"><span>Browser request</span><span>Route</span><span>Controller</span><span>Service / Model</span><span>Database</span><span>Blade response</span></div>
 <h2>Keep the existing deep reference</h2><p>The companion file <code>docs/SYSTEM_ARCHITECTURE_TUTORIAL.md</code> contains a long-form architectural assessment. This course focuses on teaching; that document is useful when you want more operational detail.</p>`
},
{
 id:'map', section:'Start here', shortTitle:'Project map', title:'The Project Map: What Every Folder Is For', time:'20 min', level:'Beginner',
 summary:'Before reading individual lines, learn where Laravel puts each responsibility and which directories are authored code versus installed dependencies.', search:'folders app config database resources routes tests public vendor node_modules storage bootstrap',
 html:`<h2>First-party folders</h2>${files([
 ['app/','PHP application behavior: models, controllers, services, policies, observers, jobs, mail, and the Filament panel.'],['routes/','Maps incoming URLs to controller methods.'],['resources/views/','Blade HTML templates for guest pages, emails, and custom admin pieces.'],['resources/js/','Your JavaScript, including the panorama viewer and tour editor.'],['resources/css/','Tailwind entry styles and the project theme.'],['database/','Migrations define tables; seeders and factories create starter or test data.'],['config/','Framework and integration settings, mostly driven by environment variables.'],['tests/','Automated examples of expected system behavior.'],['public/','The web server’s public root and compiled/static assets.'],['docs/','Human documentation, including this course.']])}
 <h2>Usually read, rarely edit</h2>${files([['vendor/','Composer-installed PHP packages such as Laravel and Filament.'],['node_modules/','npm-installed frontend packages.'],['storage/','Logs, caches, sessions, generated views, and uploaded files.'],['bootstrap/cache/','Laravel’s generated optimization files.']])}
 ${callout('analogy','A library analogy','The <code>app</code> folder is the book you wrote. <code>vendor</code> and <code>node_modules</code> are reference books you purchased. Do not rewrite a purchased book to correct your own story—extend or configure it from first-party code.')}
 <h2>Root files</h2><p><code>composer.json</code> describes PHP dependencies and autoloading. <code>package.json</code> describes browser tooling. <code>artisan</code> is Laravel’s command-line doorway. <code>vite.config.js</code> bundles CSS/JavaScript. <code>phpunit.xml</code> configures tests. <code>.env</code> holds machine-specific secrets and must not be committed.</p>`
},
{
 id:'boot', section:'Laravel foundations', shortTitle:'Boot process', title:'How Laravel Starts the Application', time:'25 min', level:'Beginner', summary:'Follow execution from public/index.php through bootstrap/app.php and learn why configuration and providers exist.', search:'public index bootstrap app middleware exceptions providers environment',
 html:`<h2>The public entry point</h2>${code('public/index.php',`<?php

use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());`)}
 ${lines([['1','Enter PHP mode.'],['3','Import Laravel’s Request class so its short name can be used.'],['5','Record the start time for performance measurement.'],['7–9','If the app is in maintenance mode, load the maintenance response.'],['11','Load Composer’s autoloader so PHP can locate Laravel and App classes.'],['14','Build and configure the Laravel application.'],['16','Capture the HTTP request and let Laravel process it.']])}
 <h2>Application configuration</h2><p><code>bootstrap/app.php</code> selects route files, middleware, and exception handling. <code>bootstrap/providers.php</code> lists service providers. Providers perform application-level registration and boot work; this project uses <code>AppServiceProvider</code> for observers and shared behavior and <code>AdminPanelProvider</code> for Filament.</p>
 ${callout('analogy','Starting a theater','The entry file unlocks the theater. The bootstrap file turns on the lights and assigns entrances. Providers prepare specialized crews. Only then can the audience’s request be handled.')}`
},
{
 id:'routes', section:'Laravel foundations', shortTitle:'Routes', title:'Routes: The Application’s Address Book', time:'35 min', level:'Beginner', summary:'Read routes/web.php and learn HTTP verbs, route names, parameters, groups, guards, throttling, signed links, and spam protection.', search:'route get post put prefix middleware throttle signed csrf guest auth web php',
 html:`<h2>A simple route</h2>${code('routes/web.php',`Route::get('/rooms/{roomType}', [GuestController::class, 'roomDetail'])
    ->name('guest.room-detail');`)}${lines([['1','Accept a GET request. <code>{roomType}</code> is a variable URL segment. Laravel resolves it to a RoomType model through route-model binding.'],['1','Send the request to the <code>roomDetail</code> method of <code>GuestController</code>.'],['2','Give the route a stable name so code can generate its URL without hard-coding <code>/rooms/...</code>.']])}
 <h2>A protected write route</h2>${code('routes/web.php',`Route::post('/reserve', [GuestController::class, 'reserveSubmit'])
    ->middleware(['throttle:5,1', ProtectAgainstSpam::class])
    ->name('guest.reserve.submit');`)}${lines([['1','POST means the request intends to create or change data.'],['2','Allow at most five attempts per minute and reject honeypot-detected bots.'],['3','Assign a reusable route name.']])}
 <h2>Route families</h2><ul><li>Public pages: home, rooms, reservation, tracking, support, and tours.</li><li>Guest account: registration, login, verification, profile, reservations, feedback, and support threads.</li><li>Tour API: JSON waypoints, availability, and reservation submission.</li><li>Payments: tokenized payment page, result pages, QR code, and PayMongo webhook.</li><li>Admin helpers: authenticated backup upload and encrypted QR generation.</li></ul>
 ${callout('note','Security vocabulary','A guard decides which kind of user is logged in. Middleware is a checkpoint around a route. Throttling limits repeated requests. A signed URL proves Laravel created the link and that its parameters were not altered.')}`
},
{
 id:'mvc', section:'Laravel foundations', shortTitle:'MVC request', title:'One Complete Request: Route → Controller → View', time:'40 min', level:'Beginner', summary:'Trace a guest page end to end and understand controllers, query building, compact(), Blade rendering, and response data.', search:'mvc guestcontroller home rooms blade view eloquent compact response',
 html:`<h2>The controller’s role</h2><p>Open <code>app/Http/Controllers/GuestController.php</code>. Its page methods receive a request, ask models or services for information, and return a Blade view. Controllers coordinate; they should not become the permanent home of every business rule.</p>
 ${code('Representative Laravel controller pattern',`public function roomDetail(RoomType $roomType)
{
    abort_unless($roomType->is_active, 404);

    $roomType->load(['amenities', 'rooms.floor']);

    return view('guest.room-detail', compact('roomType'));
}`)}
 ${lines([['1','Declare a public method. Laravel injects the RoomType matching the URL parameter.'],['3','Stop with “Not Found” if guests should not see this room type.'],['5','Eager-load related amenities, rooms, and each room’s floor to avoid repeated database queries.'],['7','Render <code>resources/views/guest/room-detail.blade.php</code> and provide a variable named <code>$roomType</code>.']])}
 <h2>Blade receives the data</h2>${code('Blade example',`<h1>{{ $roomType->name }}</h1>

@foreach ($roomType->amenities as $amenity)
    <span>{{ $amenity->name }}</span>
@endforeach`)}${lines([['1','Double braces safely escape and print the room name.'],['3','Loop through the loaded amenity collection.'],['4','Print each amenity name. Blade escapes HTML by default, reducing XSS risk.'],['5','End the loop.']])}
 ${callout('analogy','Restaurant analogy','The route is the host who directs the order. The controller is the waiter. The model/database is the kitchen. The Blade view plates the result. A waiter coordinates the meal but should not cook every dish.')}`
},
{
 id:'models', section:'Data and domain', shortTitle:'Models', title:'Eloquent Models: PHP Objects for Database Records', time:'50 min', level:'Beginner', summary:'Learn fillable fields, casts, relationships, accessors, scopes, and the central Reservation model.', search:'model eloquent reservation room roomtype guest fillable casts belongsTo hasMany scope accessor',
 html:`<h2>A model’s common parts</h2>${code('Simplified app/Models/Room.php',`class Room extends Model
{
    protected $fillable = ['room_number', 'room_type_id', 'floor_id', 'status'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}`)}${lines([['1','Extend Eloquent’s Model to gain querying and persistence behavior.'],['3','Allow only these fields in mass assignment such as <code>Room::create($data)</code>.'],['5–8','Convert database values into reliable PHP types.'],['10–13','Declare that each room belongs to one room type. The method enables <code>$room->roomType</code>.']])}
 <h2>The main domain records</h2>${files([['Reservation','The booking header: dates, status, preferred room type, totals, tokens, and workflow state.'],['Guest / GuestAccount','A staying person versus a reusable authenticated website account.'],['RoomType / Room / Floor','The accommodation category, physical inventory unit, and location hierarchy.'],['RoomHold / RoomAssignment','Temporary approved capacity versus an actual checked-in placement.'],['ReservationCharge / ReservationPayment','What is owed versus what was paid.'],['CheckInSnapshot','A historical freeze of check-in calculations.'],['TourWaypoint / TourHotspot','360° scenes and interactive points inside them.'],['SupportInquiry / SupportInquiryReply','A guest help conversation and its messages.']])}
 <h2>Read models in this order</h2><p>Start with <code>Reservation.php</code>, then follow each relationship method to <code>RoomType</code>, <code>Guest</code>, <code>RoomAssignment</code>, <code>ReservationCharge</code>, and <code>ReservationPayment</code>. Tests under <code>tests/Unit/Models</code> reveal what each relationship and cast is expected to do.</p>`
},
{
 id:'database', section:'Data and domain', shortTitle:'Database', title:'Migrations, Seeders, and the Shape of Stored Data', time:'45 min', level:'Beginner', summary:'Understand how table history is declared, why foreign keys matter, and how seed/test data differ from production data.', search:'migration schema create table foreign key index seeder factory database',
 html:`<h2>A migration is a versioned construction instruction</h2>${code('Typical migration structure',`return new class extends Migration {
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};`)}${lines([['1','Return an anonymous migration class.'],['2–11','<code>up()</code> applies the change.'],['4','Create a table and describe it through a Blueprint.'],['5','Create the primary key named <code>id</code>.'],['6–7','Add business fields with types and defaults.'],['8','Add <code>created_at</code> and <code>updated_at</code>.'],['13–16','<code>down()</code> reverses the change.']])}
 <h2>Project migration history</h2><p>The numbered 2026 migrations first create the core lodging tables, then add later capabilities: virtual tours, room holds, performance indexes, guest accounts, room requests, feedback, support, and alternative offers. Never edit an already-deployed historical migration merely to change today’s schema; add a new migration.</p>
 ${callout('analogy','Building permits','Each migration is a dated permit describing one renovation. The current building is the result of every permit applied in order. A seeder furnishes rooms with starter data; a factory produces disposable sample records for tests.')}`
},
{
 id:'reservation', section:'Feature journeys', shortTitle:'Reservation journey', title:'Reservation Journey: Search, Validate, Re-check, Save', time:'60 min', level:'Beginner → Intermediate', summary:'Trace the most important workflow from available rooms to a safely persisted reservation and room requests.', search:'reserve availability dates overlap validate transaction reservation workflow room requests',
 html:`<h2>The end-to-end flow</h2><div class="flow"><span>GET /rooms</span><span>Select dates</span><span>POST /reserve</span><span>Validate input</span><span>Re-check availability</span><span>Transaction saves</span></div>
 <h2>Why availability is checked twice</h2><p>The rooms page gives a helpful preview, but another guest may reserve between viewing and submitting. The POST handler must re-check using fresh database state. Client-side JavaScript improves experience; it never becomes the authority.</p>
 ${code('Typical validation pattern',`$validated = $request->validate([
    'check_in_date' => ['required', 'date', 'after_or_equal:today'],
    'check_out_date' => ['required', 'date', 'after:check_in_date'],
    'guest_count' => ['required', 'integer', 'min:1'],
]);`)}${lines([['1','Ask Laravel to validate and return only validated fields.'],['2','Check-in is required, must be a date, and cannot be in the past.'],['3','Check-out must be later than check-in.'],['4','Guest count must be a whole number of at least one.']])}
 <h2>Overlap rule</h2>${code('Conceptual date overlap',`existing.check_in < requested.check_out
AND
existing.check_out > requested.check_in`)}<p>This half-open interval permits one guest to check out on the day another checks in. Conflicts can come from reservations, active assignments, and unexpired holds, so shared services must consider all sources.</p>
 <h2>Where to read</h2>${files([['app/Http/Controllers/GuestController.php','Public browsing and standard reservation submission.'],['app/Services/ReservationWorkflowService.php','Reservation status transitions and business workflow.'],['app/Support/ReservationRoomRequests.php','Normalizes and persists multi-room requests.'],['app/Models/Reservation.php','Relations, status helpers, identifiers, and dates.'],['tests/Feature/GuestControllerTest.php','Executable examples of guest reservation behavior.']])}`
},
{
 id:'checkin', section:'Feature journeys', shortTitle:'Check-in/out', title:'Check-in, Pricing, Assignments, and Checkout', time:'60 min', level:'Intermediate', summary:'Understand why CheckInService owns a transaction spanning guests, room assignments, charges, snapshots, and room status.', search:'checkin checkout service transaction pricing charge payment assignment snapshot discount balance',
 html:`<h2>Why this is a service</h2><p>Check-in changes several tables that must agree. <code>app/Services/CheckInService.php</code> centralizes the workflow so the Filament page is not the only place that knows the rules.</p>
 ${code('Transaction pattern',`return DB::transaction(function () use ($reservation, $data) {
    $guests = $this->createGuests($reservation, $data);
    $assignments = $this->assignRooms($reservation, $guests, $data);
    $this->createLedgerEntries($reservation, $data);
    $this->storeSnapshot($reservation, $data);
    $reservation->update(['status' => 'checked_in']);

    return $assignments;
});`)}${lines([['1','Start a database transaction. Any exception rolls all enclosed writes back.'],['2','Create or normalize the people staying.'],['3','Place guests into physical rooms.'],['4','Create charge/payment ledger records.'],['5','Freeze the calculation inputs for later audit.'],['6','Move the reservation into checked-in state.'],['8','Return the created assignments after a successful commit.']])}
 ${callout('analogy','One receipt, several ledgers','Imagine a cashier stamping five related forms. If the fourth form fails, keeping the first three would create a contradiction. The database transaction tears up the whole incomplete set.')}
 <h2>Observers after assignments</h2><p><code>RoomAssignmentObserver</code> recalculates physical room status when assignments open or close. This keeps room state synchronized even when different screens initiate the change.</p>
 <h2>Checkout</h2><p>Checkout closes active assignments, records departure, changes reservation status, and allows the observer to recalculate room availability. Read <code>tests/Unit/Services/CheckInService*Test.php</code> beside the service.</p>`
},
{
 id:'payments', section:'Feature journeys', shortTitle:'Payments', title:'Online Payments, Tokens, Webhooks, and Idempotency', time:'55 min', level:'Intermediate', summary:'Follow a payment from guest checkout initialization to PayMongo’s asynchronous webhook and learn the security boundaries.', search:'paymongo webhook gateway payment token signature idempotent queue qr payment link',
 html:`<h2>Payment sequence</h2><div class="flow"><span>Tokenized link</span><span>Initialize checkout</span><span>PayMongo page</span><span>Webhook arrives</span><span>Queued processing</span><span>Ledger updates</span></div>
 <h2>Key classes</h2>${files([['GuestPaymentController','Validates the reservation payment token and serves/initializes checkout.'],['PaymentGatewayService','Owns requests and responses for the external gateway.'],['PaymentWebhookController','Receives quickly, verifies the request boundary, and dispatches work.'],['ProcessPaymentWebhook','Processes the event outside the short webhook response.'],['ReservationPayment','Stores gateway references, amount, method, and status.'],['PaymentGatewayException','Carries integration failures without pretending they are normal validation errors.']])}
 <h2>Idempotency</h2><p>A provider may deliver the same event more than once. Processing must search for the gateway’s unique reference before creating a payment. “Already processed” should be a successful no-op, not a duplicate ledger entry.</p>
 ${callout('analogy','Registered mail','The payment token is a claim ticket, the webhook signature is the courier’s seal, and idempotency is the receiving clerk checking the package number before adding it to inventory.')}
 <h2>Security checklist</h2><ul><li>Never expose secret API keys in JavaScript or Blade.</li><li>Do not trust a browser redirect as proof of payment; rely on verified gateway state/webhooks.</li><li>Use tokens or signed/encrypted URLs instead of numeric identifiers alone.</li><li>Return promptly from webhooks; slow work belongs in the queued job.</li></ul>`
},
{
 id:'accounts', section:'Feature journeys', shortTitle:'Guest accounts', title:'Guest Accounts, Support, Feedback, and Alternative Offers', time:'55 min', level:'Intermediate', summary:'Explore the newer guest portal and see how authentication, ownership checks, messages, feedback, and expiring offers fit around reservations.', search:'guest account auth register login verify profile dashboard claim support inquiry reply feedback alternative offer expire',
 html:`<h2>Two meanings of guest</h2><p><code>Guest</code> represents a person attached to a stay. <code>GuestAccount</code> represents login credentials and a reusable website identity. Keeping them separate lets a reservation contain multiple occupants while only one account owns the online relationship.</p>
 <h2>Account route lifecycle</h2><div class="flow"><span>Register</span><span>Verify email</span><span>Login</span><span>Claim reservation</span><span>View dashboard</span><span>Leave feedback</span></div>
 <h2>Controllers by responsibility</h2>${files([['Guest/AuthController.php','Registration, login, logout, verification, and resend flow.'],['Guest/PasswordResetController.php','Request and consume password-reset tokens.'],['Guest/DashboardController.php','Dashboard, reservation lists/details, and claim workflow.'],['Guest/ProfileController.php','Edit authenticated guest-account profile data.'],['Guest/FeedbackController.php','Create feedback only for eligible owned reservations.'],['Guest/SupportThreadController.php','Open inquiries, show authorized threads, poll messages, and reply.'],['AlternativeRoomOfferController.php','Display and accept/decline a valid offer token.'],['AlternativeRoomOfferService.php','Creates offers, expires them, and applies accepted alternatives.']])}
 <h2>Ownership is not optional</h2><p>Authentication answers “who are you?” Authorization answers “may you access this record?” A logged-in guest must still be prevented from changing a reservation, inquiry, or feedback record owned by another account.</p>
 ${callout('note','Learn from the tests','The feature tests named <code>GuestAccountDashboardTest</code>, <code>GuestSupportInquiryTest</code>, and <code>AlternativeRoomOfferExpiryTest</code> are concise specifications of edge cases and access rules.')}`
},
{
 id:'filament', section:'Staff application', shortTitle:'Filament admin', title:'How the Filament Staff Panel Is Built', time:'60 min', level:'Intermediate', summary:'Decode Resources, Pages, RelationManagers, Widgets, actions, forms, tables, and the especially important ReservationResource.', search:'filament admin resource page widget form table action relation manager reservation resource',
 html:`<h2>Filament’s building blocks</h2>${files([['Resources','Describe one managed record type: its form, table, navigation, authorization, and routes.'],['Resource/Pages','List, create, edit, view, and custom workflows for that resource.'],['RelationManagers','Edit child records, such as reservation payments or guests, within a parent.'],['Widgets','Dashboard summaries, charts, recent bookings, and the reservation calendar.'],['Pages','Standalone tools such as reports, backups, settings, and permission reference.']])}
 ${code('Typical resource pattern',`class FloorResource extends Resource
{
    protected static ?string $model = Floor::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
        ]);
    }
}`)}${lines([['1','A resource is a configuration class, not a database model.'],['3','Tell Filament which Eloquent model it manages.'],['5–10','Define fields shown when staff create or edit. Chained calls add rules.'],['12–17','Define list columns and their behaviors.']])}
 <h2>ReservationResource is the hub</h2><p>It combines status actions, room availability, approval, payment links, check-in/out, force checkout, relations, filters, and display formatting. Read it after understanding the smaller Floor, Amenity, Service, RoomType, and Room resources.</p>
 ${callout('analogy','A configurable control panel','A Resource is like a blueprint for a control panel: which gauges appear, which switches staff can use, and what each switch is allowed to change. The model remains the underlying machine.')}`
},
{
 id:'security', section:'Staff application', shortTitle:'Security', title:'Policies, Roles, Permissions, and Data Boundaries', time:'45 min', level:'Intermediate', summary:'Learn how staff authorization works, why hiding a button is insufficient, and where public input is constrained.', search:'policy authorization role permission user policy csrf xss validation mass assignment security',
 html:`<h2>A policy method</h2>${code('Representative policy pattern',`public function update(User $user, Reservation $reservation): bool
{
    return $user->hasPermission('reservations.update');
}`)}${lines([['1','Laravel supplies the authenticated staff user and the target record.'],['3','Return a boolean based on the project’s permission system.']])}
 <h2>Seven protected resources</h2><p>Policies exist for Amenity, Floor, Reservation, Room, RoomType, Service, and User. Filament consults these policies when showing pages and performing actions. User management receives extra protection so privilege changes are not treated like ordinary edits.</p>
 <h2>Defense layers</h2><ul><li><strong>Routes and middleware:</strong> authentication, throttling, signatures, spam protection.</li><li><strong>Validation:</strong> shape and constrain untrusted input.</li><li><strong>Policies/ownership:</strong> decide who may act on a specific record.</li><li><strong>Models:</strong> limit mass assignment and cast values.</li><li><strong>Blade:</strong> escape output unless raw HTML is intentionally trusted.</li><li><strong>Database:</strong> enforce uniqueness, references, and atomic transactions.</li></ul>
 ${callout('analogy','Airport security','No single checkpoint does everything. A ticket check, identity check, luggage scan, and gate check defend different boundaries. Likewise, a hidden admin button cannot replace server-side authorization.')}`
},
{
 id:'automatic', section:'Behind the scenes', shortTitle:'Observers & jobs', title:'Observers, Mail, Notifications, Commands, and Queues', time:'50 min', level:'Intermediate', summary:'Understand automatic side effects and scheduled/background work without losing track of when the code runs.', search:'observer job queue command schedule mail notification event side effect cron',
 html:`<h2>Observers react to model events</h2><p>The seven observers watch Amenities, Floors, Reservations, Rooms, RoomAssignments, RoomTypes, and Services. They update derived state, create notifications/logs, or keep related records synchronized after create/update/delete events.</p>
 ${code('Observer shape',`class RoomAssignmentObserver
{
    public function saved(RoomAssignment $assignment): void
    {
        $this->recalculateRoomStatus($assignment->room);
    }

    public function deleted(RoomAssignment $assignment): void
    {
        $this->recalculateRoomStatus($assignment->room);
    }
}`)}<p>Observers make behavior consistent across callers, but they are less visible than a direct method call. Whenever a model save seems to cause “extra” work, check its observer.</p>
 <h2>Background and scheduled work</h2>${files([['ProcessPaymentWebhook','Processes gateway events after the HTTP response.'],['RestoreDatabaseJob','Runs a sensitive, potentially long restore workflow.'],['ExpireUnpaidReservations','Finds and expires reservations beyond their payment window.'],['ExpireAlternativeRoomOffers','Closes offers after their expiry.'],['SendNearDueReservationReminders','Notifies staff about upcoming due reservations.'],['PurgeNotifications','Removes old database notifications.'],['RepairNotificationLinks','Repairs link data from earlier notification formats.']])}
 <h2>Email and notifications</h2><p>Mail classes package a reservation status message, payment link, or alternative offer with Blade email templates. <code>NotificationHelper</code> and <code>FilamentDatabaseNotification</code> create staff-facing database notifications.</p>`
},
{
 id:'frontend', section:'Frontend', shortTitle:'Blade & CSS', title:'Guest Views, Blade Components, Tailwind, and Vite', time:'55 min', level:'Beginner → Intermediate', summary:'Read the guest layout, page templates, reusable partials, form protection, styling pipeline, and request-aware links.', search:'blade layout yield section include component tailwind css vite asset csrf old errors media url cloudflare localhost',
 html:`<h2>The layout and page relationship</h2>${code('Blade layout pattern',`<!-- resources/views/layouts/guest.blade.php -->
<body>
    <nav>...</nav>
    <main>@yield('content')</main>
</body>

<!-- A guest page -->
@extends('layouts.guest')

@section('content')
    <h1>Rooms</h1>
@endsection`)}<p>The layout owns repeated page chrome. Each page fills the named <code>content</code> slot. Partials under <code>guest/partials</code> reuse smaller pieces such as flash messages, status badges, and star ratings.</p>
 <h2>Safe forms</h2>${code('Blade form essentials',`<form method="POST" action="{{ route('guest.reserve.submit') }}">
    @csrf
    <input name="first_name" value="{{ old('first_name') }}">
    @error('first_name') <p>{{ $message }}</p> @enderror
</form>`)}${lines([['1','Generate the action from a route name rather than hard-coding a host.'],['2','Add a CSRF token so Laravel can reject forged cross-site submissions.'],['3','Restore the previous safe input after a validation failure.'],['4','Show the validation message for this field.']])}
 <h2>Styling pipeline</h2><p><code>resources/css/app.css</code> is the authored entry. Tailwind scans the configured PHP/Blade/JS files, PostCSS processes the result, and Vite produces browser assets. The admin has additional Filament theme configuration.</p>
 ${callout('note','Two supported environments','Internal links and media should be route-generated, request-aware, or relative. Avoid baking <code>localhost</code> or the Cloudflare hostname into templates. The <code>MediaUrl</code> support class exists for this boundary.')}`
},
{
 id:'tour', section:'Frontend', shortTitle:'Virtual tours', title:'The 360° Virtual Tour Subsystem', time:'65 min', level:'Intermediate', summary:'Connect tour database records, JSON API routes, Photo Sphere Viewer, markers, navigation, editor state, and availability actions.', search:'virtual tour panorama three photo sphere viewer waypoint hotspot marker gyroscope stereo javascript api',
 html:`<h2>Two database concepts</h2><p>A <code>TourWaypoint</code> is a panorama scene with a slug and image. A <code>TourHotspot</code> is an interactive location inside a scene—navigation, room information, media, or another action.</p>
 <h2>Runtime layers</h2>${files([['app/Http/Controllers/TourController.php','Returns viewer HTML, JSON scene data, availability, and tour-originated reservation submission.'],['resources/js/panorama-viewer.js','Low-level Photo Sphere Viewer setup.'],['resources/js/tour-engine.js','Scene loading, hotspot behavior, navigation, and public tour state.'],['resources/js/tour-editor.js','Admin editing interactions and position capture.'],['resources/js/home-tour-preview.js','Smaller preview on the guest home page.'],['VirtualTourResource + pages','Staff CRUD and hotspot management.']])}
 <h2>Fetch with a relative URL</h2>${code('Representative browser request',`const response = await fetch('/api/tour/waypoint/' + encodeURIComponent(slug), {
    headers: { Accept: 'application/json' },
});

if (!response.ok) {
    throw new Error('Unable to load tour scene.');
}

const scene = await response.json();`)}${lines([['1','Request a scene from the same current host; encode the slug before putting it in a URL.'],['2','Ask explicitly for JSON.'],['5–7','Turn an HTTP failure into a JavaScript error the UI can handle.'],['9','Parse the response body into a JavaScript object.']])}
 ${callout('analogy','A museum audio guide','The waypoint is a gallery room. The panorama is what you see while standing there. Hotspots are labels and doors. The tour engine is the guide that interprets what each label or door should do.')}`
},
{
 id:'testing', section:'Quality', shortTitle:'Tests', title:'Tests as Executable Documentation', time:'50 min', level:'Beginner → Intermediate', summary:'Learn the Arrange–Act–Assert rhythm and use the project’s model, service, policy, observer, controller, payment, and admin tests as a reading guide.', search:'phpunit test feature unit arrange act assert mock database refresh testing',
 html:`<h2>A test’s three acts</h2>${code('Typical PHPUnit feature test',`public function test_guest_can_view_active_room_type(): void
{
    $roomType = RoomType::factory()->create(['is_active' => true]);

    $response = $this->get(route('guest.room-detail', $roomType));

    $response->assertOk();
    $response->assertSee($roomType->name);
}`)}${lines([['1','Name the expected behavior in plain language.'],['3','Arrange: create the necessary state.'],['5','Act: make the same HTTP request a browser would make.'],['7–8','Assert: verify both the status and visible result.']])}
 <h2>Unit versus feature</h2><p>Unit tests focus on one model, service, policy, or observer with limited surroundings. Feature tests exercise a larger slice such as routes, middleware, database, controllers, and views together. This project has extensive suites for both.</p>
 <h2>Best reading technique</h2><ol><li>Pick a class such as <code>ReservationWorkflowService</code>.</li><li>Read the matching test method name first.</li><li>Predict how the implementation will satisfy it.</li><li>Read the implementation.</li><li>Return to the assertions and examine edge cases.</li></ol>
 ${callout('analogy','A legal contract','Implementation code describes how work is done. A good test records the promise the code must keep. When refactoring, the promise should remain true even if the internal steps change.')}`
},
{
 id:'atlas', section:'Reference', shortTitle:'File atlas', title:'Complete First-Party Code Atlas', time:'Reference', level:'All levels', summary:'Use this index to account for the entire authored codebase and choose the next file to study without getting lost in generated dependencies.', search:'all files atlas inventory controllers models services filament views javascript migrations tests config support',
 html:`<h2>Recommended reading order</h2><ol><li><code>routes/web.php</code> and <code>GuestController.php</code></li><li>Core models and their migrations</li><li>Reservation and check-in services</li><li>Small Filament resources, then ReservationResource</li><li>Guest Blade layout and pages</li><li>Tour JavaScript and controller</li><li>Observers, policies, jobs, commands, mail</li><li>Matching tests after every class</li></ol>
 <h2>Application PHP (151 first-party files)</h2>${files([['app/Models (24)','Read every record class; group them by lodging, ledger, identity/support, and tours.'],['app/Http (14)','Public/account/payment/tour/backup/QR request coordinators.'],['app/Services (7)','Check-in, holds, reservation workflow, calendar utilization, mailer, payments, and alternative offers.'],['app/Filament (81)','Resources, their pages/relation managers, widgets, admin pages, and provider.'],['app/Policies (7)','Authorization for core admin resources.'],['app/Observers (7)','Automatic reactions to model writes.'],['app/Console (6)','Scheduler kernel and maintenance/business commands.'],['app/Support (4)','Shared date, settings, media URL, and room-request helpers.'],['app/Jobs (2)','Queued webhook processing and database restoration.'],['app/Mail + Notifications (5)','Outbound guest email and staff notification packaging.'],['app/Providers + Exceptions','Application boot configuration and explicit payment failure type.']])}
 <h2>User interface</h2>${files([['resources/views/guest','Public site, payment/tour/offer pages, account portal, feedback, and support.'],['resources/views/emails','Reservation status, payment link, and alternative-offer emails.'],['resources/views/filament','Custom admin theme, branding, widgets, forms, and modal/table fragments.'],['resources/js (6)','App bootstrap plus panorama preview/viewer/engine/editor.'],['resources/css','Guest Tailwind entry and admin theme configuration.'],['public custom files','Logos/images plus authored support and chart enhancements; most public/filament assets are package-published build output.']])}
 <h2>Data, configuration, and behavior contracts</h2>${files([['database/migrations','Read chronologically: the full schema and its evolution.'],['database/seeders + factory','Current inventory, demos, virtual tours, and test users.'],['config','Laravel infrastructure plus PayMongo, media, honeypot, Livewire, and services.'],['tests/Unit','Models, services, policies, observers, and support helpers.'],['tests/Feature','Guest/admin journeys, payment webhook, check-in balance, backups, tours, calendars, and alternative offers.'],['routes','Web endpoints and console scheduling.'],['Root build/deploy files','Composer/npm dependencies, Vite/Tailwind/PostCSS, PHPUnit, XAMPP scripts, and deployment scripts.']])}
 ${callout('note','Generated files are accounted for, not taught line by line','The package-published files under <code>public/js/filament</code>, <code>public/css/filament</code>, and <code>public/js/saade</code> are compiled dependency artifacts. Learn their public behavior from Filament/plugin documentation; do not treat minified output as your project’s source code.')}
 <h2>Definition of “understood” for any file</h2><p>You should be able to answer: Who calls it? What inputs does it trust? What state does it read or change? What does it return? What can fail? Which test protects it? Which localhost/Cloudflare URL assumptions does it make?</p>`
},
{
 id:'practice', section:'Reference', shortTitle:'Study plan', title:'A Four-Week Manual Study Plan', time:'4 weeks', level:'Beginner', summary:'Turn passive reading into durable understanding through tracing, prediction, tiny experiments, and test-guided review.', search:'study plan exercises practice learning debug trace',
 html:`<h2>Week 1 — Laravel language</h2><p>Map folders, trace three GET routes, learn Blade syntax, and read five small models with their migrations. Draw the request flow from memory.</p>
 <h2>Week 2 — Reservation domain</h2><p>Trace room discovery and reservation submission. Write the overlap rule on paper. Follow every Reservation relationship. Read ReservationWorkflowService tests before its implementation.</p>
 <h2>Week 3 — Staff and money</h2><p>Compare a small Filament Resource with ReservationResource. Trace approval, check-in, ledger creation, checkout, payment initialization, and webhook processing.</p>
 <h2>Week 4 — Supporting systems</h2><p>Trace guest authentication/support/feedback, then the virtual-tour JSON and JavaScript. Finish with observers, policies, scheduled commands, and deployment/environment boundaries.</p>
 <h2>For every study session</h2><ol><li><strong>Predict:</strong> say what the code will do before running it.</li><li><strong>Trace:</strong> write the caller → method → query → response chain.</li><li><strong>Change mentally:</strong> ask what breaks if a line is removed.</li><li><strong>Verify:</strong> locate or write a test for the behavior.</li><li><strong>Teach back:</strong> explain it aloud without framework jargon.</li></ol>
 ${callout('analogy','Learning a city','Reading every street name once will not teach you the city. Repeatedly travel meaningful routes—guest books a room, staff approves it, guest pays, staff checks in—and the map becomes natural.')}
 <h2>Your first exercise</h2><p>Open <code>routes/web.php</code>. Choose <code>guest.rooms</code>. Trace it into the controller, list every model query, find the returned Blade file, and identify every variable used by that view. Then repeat for <code>guest.account.dashboard</code>.</p>`
}
];
