# Prepare Check-in Flow — Comprehensive Review

**Date:** April 15, 2026  
**Scope:** Room Entries section logic in the "Prepare Check-in (Pending Payment)" action  
**Files Reviewed:**
- `app/Filament/Resources/ReservationResource.php` (lines 1147-1765)
- `app/Services/CheckInService.php` (preparePendingPayment, normalizeEntriesWithPrimaryGuest)
- `app/Models/RoomHold.php`

---

## 1. INTENDED WORKFLOW

The "Prepare Check-in" action is designed to:

1. **Collect primary guest details** (name, address, contact, age, gender, ID, nationality)
2. **Pre-assign rooms and guests** before payment is collected
3. **Lock inventory** by marking rooms as `reserved` and creating short-term `RoomHold` records
4. **Calculate exact payable amount** based on room mode, guest count, nights, add-ons, and discounts
5. **Set expiration timer** (30 min to 24 hours) for the hold while guest processes payment
6. **Store complete check-in snapshot** in `checkin_hold_payload` for later finalization

This allows staff to prepare everything upfront, then send the guest to cashier with an exact amount due.

---

## 2. DETAILED COMPONENT ANALYSIS

### 2.1 Primary Guest Section
**Location:** Lines 1156-1207  
**Purpose:** Capture the main guest's personal information

**✅ Strengths:**
- Pre-populated from reservation data
- All fields are `->live()` for reactivity
- Gender selection triggers room_id reset (prevents gender mismatch)
- Properly dehydrated for form submission

**⚠️ Issues:**
- Gender `afterStateUpdated` resets `room_id` globally, but doesn't reset room_id **within the repeater items** — this could cause confusion if staff changed gender after selecting rooms
- No validation that primary guest gender matches the room type gender restriction (if any exist in your room types)

---

### 2.2 Repeater: `reservation_rooms`
**Location:** Lines 1215-1440  
**Purpose:** Allow staff to add multiple room entries, each with their own mode and guest list

#### 2.2.1 Default Pre-population (Lines 1216-1236)

```php
->default(function (Reservation $record) {
    $holds = $record->roomHolds()
        ->advance()
        ->with('room.roomType')
        ->get();

    if ($holds->isEmpty()) {
        return [];
    }

    return $holds->map(function ($hold) {
        $room = $hold->room;
        $isPrivate = $room->roomType?->isPrivate() ?? false;

        return [
            'room_mode' => $isPrivate ? 'private' : 'dorm',
            'room_id' => $room->id,
            'includes_primary_guest' => true,
            'guests' => [],
        ];
    })->toArray();
})
```

**❌ Critical Issues:**

1. **Silent null handling**: If `$hold->room` is null (room deleted/deactivated), the map continues and tries to call `?->isPrivate()` on null — this defaults to `false`, creating a misleading entry
2. **No validation**: Doesn't check if held rooms are still:
   - Active (`is_active = true`)
   - Available status-wise
   - Matching the current reservation date range
3. **Date misalignment risk**: If reservation dates were changed after approval, the advance holds may no longer be valid for the current `check_in_date` / `check_out_date`
4. **Multi-room primary guest assignment**: Sets `includes_primary_guest => true` for **ALL** held rooms, which violates the exclusivity rule enforced later
5. **Empty guest arrays**: Pre-populates with empty `guests` arrays, requiring staff to manually re-enter all companions even if they were entered during approval
6. **No feedback**: User doesn't see how many holds exist vs. how many were successfully loaded

**Recommended Fix:**
```php
->default(function (Reservation $record) {
    $holds = $record->roomHolds()
        ->advance()
        ->with('room.roomType')
        ->get();

    if ($holds->isEmpty()) {
        return [];
    }

    $validEntries = [];
    $skippedCount = 0;
    
    foreach ($holds as $index => $hold) {
        // Validate room still exists and is usable
        if (!$hold->room || !$hold->room->is_active) {
            $skippedCount++;
            continue;
        }
        
        $room = $hold->room;
        
        // Validate room is still available/usable
        if (in_array($room->status, ['maintenance', 'inactive'], true)) {
            $skippedCount++;
            continue;
        }
        
        $validEntries[] = [
            'room_mode' => $room->roomType?->isPrivate() ? 'private' : 'dorm',
            'room_id' => $room->id,
            'includes_primary_guest' => ($index === 0), // Only first room gets primary
            'guests' => [],
        ];
    }
    
    return $validEntries;
})
->helperText(function (Reservation $record) {
    $totalHolds = $record->roomHolds()->advance()->count();
    if ($totalHolds === 0) {
        return null;
    }
    return "Found {$totalHolds} room(s) held from approval. Pre-populated entries shown below.";
})
```

---

#### 2.2.2 Field: `room_mode` (Lines 1238-1262)

**✅ Strengths:**
- Clear labels ("Private (occupies whole room)" vs "Dorm (per-bed assignment)")
- Smart default based on preferred room type
- Resets conflicting fields when changed
- Preserves typed guest data (doesn't clear guests array)

**⚠️ Minor Issues:**
- The `afterStateUpdated` sets `includes_primary_guest` to `false` when mode changes — this might be too aggressive; consider preserving it if the new mode is compatible
- No validation prevents staff from selecting "private" for a dorm-type room or vice versa (though the room_id options filter helps)

---

#### 2.2.3 Field: `room_id` (Lines 1263-1335)

**✅ Strengths:**
- **Excellent filtering logic** that matches room sharing type to the selected mode
- Releases expired holds before querying (`releaseExpiredHolds()`)
- Groups by room type with preferred type first
- Shows held rooms with "(Already held)" indicator
- Dorm rooms show capacity check: `capacity > COUNT(checked_in assignments)`
- Private rooms require full availability

**❌ Critical Issues:**

1. **Performance**: Executes every time the dropdown opens (on every `options()` call). For large deployments, this could run the query dozens of times per form load.
2. **N+1 query risk**: The inner `$record->roomHolds()->advance()->where('room_id', $room->id)->exists()` check runs once per room in the result set — this is an N+1 query pattern
3. **Race condition**: Between the time staff opens the dropdown and selects a room, another staff member could assign that room elsewhere
4. **No reservation date validation**: Doesn't check if the room is on advance hold for **another reservation** during this reservation's date range
5. **Grouped optgroups can be confusing**: When preferred type has 15 rooms and non-preferred have 10, the visual hierarchy isn't obvious

**Recommended Optimizations:**
```php
->options(function ($get, Reservation $record) {
    static $cachedOptions = null;
    static $lastMode = null;
    static $lastRecordId = null;
    
    $mode = $get('room_mode');
    
    // Cache options for same mode + record to avoid repeated queries
    if ($cachedOptions !== null 
        && $lastMode === $mode 
        && $lastRecordId === $record->id) {
        return $cachedOptions;
    }
    
    app(CheckInService::class)->releaseExpiredHolds();
    
    if (!in_array($mode, ['private', 'dorm'], true)) {
        return [];
    }
    
    $preferredTypeId = $record->preferred_room_type_id;
    
    // Get all held room IDs for this reservation in one query
    $heldRoomIds = $record->roomHolds()
        ->advance()
        ->pluck('room_id')
        ->toArray();
    
    $query = Room::query()
        ->with('roomType')
        ->where('is_active', true)
        ->whereHas('roomType', function ($q) use ($mode) {
            if ($mode === 'private') {
                $q->where('room_sharing_type', 'private');
            } else {
                $q->where('room_sharing_type', '!=', 'private');
            }
        });
    
    if ($mode === 'dorm') {
        $query->whereIn('status', ['available', 'occupied'])
            ->whereRaw('capacity > (
                SELECT COUNT(*) FROM room_assignments
                WHERE room_assignments.room_id = rooms.id
                AND room_assignments.status = ?
            )', ['checked_in']);
    } else {
        $query->where('status', 'available');
    }
    
    $rooms = $query->get();
    
    if ($rooms->isEmpty()) {
        $cachedOptions = ['' => '(No available rooms)'];
        $lastMode = $mode;
        $lastRecordId = $record->id;
        return $cachedOptions;
    }
    
    // Group and build options
    $grouped = $rooms->groupBy('room_type_id')
        ->sortBy(fn($group, $typeId) => $typeId == $preferredTypeId ? 0 : 1);
    
    $options = [];
    foreach ($grouped as $typeId => $roomsInType) {
        $typeName = $roomsInType->first()->roomType->name;
        $isPreferred = $typeId == $preferredTypeId;
        $groupLabel = $isPreferred ? "✓ {$typeName} (Preferred)" : $typeName;
        
        $options[$groupLabel] = $roomsInType->mapWithKeys(function ($room) use ($heldRoomIds) {
            $isHeld = in_array($room->id, $heldRoomIds, true);
            $label = "Room {$room->room_number}".($isHeld ? ' 🔒' : '');
            return [$room->id => $label];
        })->toArray();
    }
    
    $cachedOptions = $options;
    $lastMode = $mode;
    $lastRecordId = $record->id;
    
    return $cachedOptions;
})
```

---

#### 2.2.4 Toggle: `includes_primary_guest` (Lines 1337-1377)

**Purpose:** Ensure primary guest is only assigned to one room entry

**✅ Strengths:**
- Enforces exclusivity: only one room can have the primary guest
- Advanced state path parsing to identify which repeater item was toggled
- Prevents toggle flicker by only acting on ON events

**❌ Critical Issues:**

1. **Complex state path logic**: The `getStatePath()` parsing with manual array key extraction is fragile and could break if Filament changes its internal state structure
2. **Pre-population conflict**: As noted earlier, the default() function sets this to `true` for all held rooms, which conflicts with this exclusivity rule
3. **No visual feedback**: When staff toggles one room ON and the system auto-toggles all others OFF, there's no notification explaining what happened
4. **Race condition**: If staff rapidly clicks multiple toggles, the state updates might not sync properly

**Recommended Simpler Approach:**
```php
->afterStateUpdated(function ($state, $get, $set, $livewire) {
    // Only process when toggled ON
    if ($state !== true) {
        return;
    }
    
    // Use Livewire's form data instead of parsing state paths
    $allEntries = $get('../../reservation_rooms') ?? [];
    if (!is_array($allEntries) || empty($allEntries)) {
        return;
    }
    
    // Get current item index from Livewire
    $currentPath = $livewire->getMountedActionFormComponentStatePath();
    preg_match('/reservation_rooms\.(\d+)\.includes_primary_guest/', $currentPath, $matches);
    $currentIndex = $matches[1] ?? null;
    
    if ($currentIndex === null) {
        return;
    }
    
    // Turn off all other toggles
    foreach ($allEntries as $index => $entry) {
        if ((string)$index !== (string)$currentIndex) {
            $set("../../reservation_rooms.{$index}.includes_primary_guest", false);
        }
    }
    
    // Show notification
    Notification::make()
        ->title('Primary guest assigned to this room')
        ->body('Primary guest has been moved to this room entry.')
        ->success()
        ->send();
})
```

---

#### 2.2.5 Nested Repeater: `guests` (Lines 1378-1418)

**Purpose:** Add companion guests for the selected room

**✅ Strengths:**
- Dynamic label shows selected room number
- Compact 5-column layout
- Clear helper text explaining primary guest is separate
- Doesn't allow reordering (prevents confusion)
- Only visible when room is selected

**⚠️ Issues:**

1. **No capacity validation**: Staff can add 10 guests to a 4-bed dorm room — no live validation against `room.capacity`
2. **No duplicate detection**: Staff could accidentally add the same guest twice
3. **Gender mismatch**: No validation that guest gender matches room's gender restriction (if room types have gender rules)
4. **Age validation too loose**: Allows ages 1-120, but university homestays typically have age restrictions
5. **No auto-complete**: If the same guest appears in multiple reservations, staff must re-type everything

**Recommended Enhancements:**
```php
->maxItems(function ($get) {
    $roomId = $get('room_id');
    if (!$roomId) {
        return 10; // Default
    }
    
    $room = \App\Models\Room::find($roomId);
    if (!$room) {
        return 10;
    }
    
    $mode = $get('room_mode');
    if ($mode === 'private') {
        return 10; // No hard limit for private
    }
    
    // For dorm: limit based on capacity minus occupied slots
    $occupied = $room->roomAssignments()
        ->where('status', 'checked_in')
        ->count();
    
    $includesPrimary = $get('includes_primary_guest') ? 1 : 0;
    
    return max(1, $room->capacity - $occupied - $includesPrimary);
})
->helperText(function ($get) {
    $roomId = $get('room_id');
    $mode = $get('room_mode');
    
    if (!$roomId || $mode !== 'dorm') {
        return 'Add companion guests only. Primary guest is auto-included when enabled above.';
    }
    
    $room = \App\Models\Room::find($roomId);
    if (!$room) {
        return null;
    }
    
    $occupied = $room->roomAssignments()->where('status', 'checked_in')->count();
    $available = $room->capacity - $occupied;
    
    return "Room capacity: {$room->capacity} | Occupied: {$occupied} | Available slots: {$available}";
})
```

---

### 2.3 Service Layer: `preparePendingPayment()`

**Location:** `app/Services/CheckInService.php` lines 218-391

**✅ Strengths:**
- Releases expired holds first (prevents stale data)
- Validates reservation status
- Normalizes primary guest insertion via `normalizeEntriesWithPrimaryGuest()`
- Uses DB transaction for atomicity
- Creates both room status changes AND RoomHold records
- Stores complete snapshot for finalization
- Records audit log

**❌ Issues:**

1. **Validation happens too late**: Room availability is checked in the service layer during submission — errors force staff to start over
2. **Status toggling fragility**: Setting room status to `reserved` works, but if the hold expires and is released, `recalculateStatus()` is called — this might not restore the room to the correct state if there are race conditions
3. **No overlap detection**: Doesn't check if another reservation's short-term hold overlaps this reservation's dates
4. **Primary guest normalization is confusing**: The `$fallbackToFirstRoom` parameter defaults to `true` in `execute()` but the logic force-inserts primary guest even if staff didn't toggle any room
5. **Held guest count calculation**: Counts all guests in entries, but doesn't validate against room capacity

---

### 2.4 Primary Guest Normalization Logic

**Location:** `app/Services/CheckInService.php` lines 1045-1091

```php
private function normalizeEntriesWithPrimaryGuest(array $entries, array $primaryGuest, bool $fallbackToFirstRoom): array
{
    if (empty($entries)) {
        return $entries;
    }

    // Find which entries have includes_primary_guest = true
    $primaryIndices = [];
    foreach ($entries as $index => $entry) {
        if ((bool) ($entry['includes_primary_guest'] ?? false)) {
            $primaryIndices[] = $index;
        }
    }

    // Validate exactly one room has primary guest
    if (count($primaryIndices) > 1) {
        throw new \RuntimeException('Primary guest can only be included in one room entry.');
    }

    // If none selected, auto-assign to first room (if fallback enabled)
    if (count($primaryIndices) === 0) {
        if (! $fallbackToFirstRoom) {
            throw new \RuntimeException('Please choose one room entry to include the primary guest.');
        }

        $primaryIndices = [0];
        $entries[0]['includes_primary_guest'] = true;
    }

    // Insert primary guest data at the beginning of that room's guest array
    $primaryIndex = $primaryIndices[0];
    $entries[$primaryIndex]['guests'] = $entries[$primaryIndex]['guests'] ?? [];

    $hasPrimaryGuest = collect($entries[$primaryIndex]['guests'])
        ->contains(fn ($guest) => (bool) ($guest['_is_primary'] ?? false));

    if (! $hasPrimaryGuest) {
        array_unshift($entries[$primaryIndex]['guests'], [
            'first_name' => $primaryGuest['first_name'],
            'last_name' => $primaryGuest['last_name'],
            'middle_initial' => $primaryGuest['middle_initial'],
            'gender' => $primaryGuest['gender'],
            'age' => $primaryGuest['age'] ?? null,
            'full_address' => $primaryGuest['full_address'],
            'contact_number' => $primaryGuest['contact_number'],
            '_is_primary' => true,
        ]);
    }

    return $entries;
}
```

**✅ Strengths:**
- Validates exclusivity (only one room can have primary)
- Auto-prepends primary guest to the selected room's guest array
- Uses `_is_primary` marker for later identification
- Checks for duplicate insertion

**⚠️ Issues:**

1. **Silent fallback**: When `$fallbackToFirstRoom = true` (default in execute()), silently assigns primary to first room even if staff explicitly left all toggles OFF
2. **Implicit contract**: The toggle field in the form must be named exactly `includes_primary_guest` — no validation or documentation
3. **Guest array mutation**: Modifying the entries array directly can cause unexpected behavior if the form is re-rendered

---

## 3. CRITICAL ISSUES SUMMARY

### High Priority:
1. **Pre-population doesn't handle deleted/inactive rooms** → Silent failures, misleading entries
2. **N+1 query in room_id options()** → Performance degradation at scale
3. **No capacity validation in guest repeater** → Staff can overbook rooms
4. **Multi-room primary guest conflict** → Pre-population violates exclusivity rule
5. **No date validation on advance holds** → Held rooms might not match current reservation dates

### Medium Priority:
6. **No feedback when holds fail to load** → Staff doesn't know why rooms aren't pre-filled
7. **Complex state path parsing in toggle** → Fragile, could break with Filament updates
8. **Race conditions on room availability** → One staff could steal a room from another mid-form
9. **No duplicate guest detection** → Can add same person twice
10. **Silent primary guest fallback** → Confusing UX when staff doesn't select a room

### Low Priority:
11. **No auto-complete for repeat guests** → Tedious data entry
12. **Gender validation not enforced** → Could assign mismatched genders to gendered dorms
13. **Optgroup visual hierarchy** → Hard to distinguish preferred vs non-preferred types
14. **Empty guest arrays in pre-population** → Missed opportunity to restore approval-stage guest data

---

## 4. STRENGTHS TO PRESERVE

1. **Flexible room mode selection** — allows mixing private and dorm rooms in one reservation
2. **Real-time pricing calculation** — staff sees exact amount before committing
3. **Transaction integrity** — DB transaction ensures atomicity
4. **Audit logging** — every step is recorded in ReservationLog
5. **Hold expiration system** — prevents indefinite inventory locks
6. **Advance hold integration** — respects approval-stage room assignments
7. **Primary guest auto-inclusion** — reduces redundant data entry

---

## 5. RECOMMENDED IMPROVEMENTS

### Immediate Fixes (< 1 hour):
1. **Add hold-loading feedback** — Show "X rooms held, Y pre-populated, Z skipped" message
2. **Filter null rooms in pre-population** — Skip deleted/inactive held rooms with user notice
3. **Fix primary guest multi-toggle** — Only set first held room to `includes_primary_guest = true`
4. **Add capacity helper text** — Show available slot count for dorm rooms

### Short-term Enhancements (1-4 hours):
5. **Cache room options** — Avoid re-querying on every dropdown open
6. **Optimize hold check** — Load all held room IDs in one query, then filter in-memory
7. **Add duplicate guest detection** — Warn if same name appears twice
8. **Add capacity validation** — Prevent exceeding room capacity in real-time

### Medium-term Features (1-2 days):
9. **Visual hold status indicator** — Badge showing "3/5 held rooms loaded successfully"
10. **Smart primary guest placement** — If no toggle selected, suggest best room based on gender/type
11. **Guest auto-complete** — Pull from recent reservations or guest database
12. **Date validation on holds** — Check if pre-populated rooms still match current reservation dates

### Long-term Refactors (1 week+):
13. **Extract RepeaterEntry component** — Create dedicated Livewire component for room entries
14. **Add wizard steps** — Break into: Room Selection → Guest Assignment → Confirmation → Hold Creation
15. **Real-time availability updates** — WebSocket-based live room status (prevent race conditions)
16. **AI-assisted room assignment** — Suggest optimal room allocation based on group composition

---

## 6. ARCHITECTURAL RECOMMENDATIONS

### Consider a State Machine Approach:
The current "Prepare Check-in" flow mixes UI logic, validation, and business rules. Consider:

```
States: 
  - draft (form open, not submitted)
  - validating (checking room availability)
  - holding (rooms locked, awaiting payment)
  - expired (hold timed out)
  - finalized (payment received, checked in)

Transitions:
  draft → validating: Submit form
  validating → holding: Validation passed
  validating → draft: Validation failed (show errors)
  holding → finalized: Payment received
  holding → expired: Timer ran out
  expired → draft: Reset and retry
```

This makes the flow more predictable and testable.

### Separate Concerns:
- **Form Layer** (ReservationResource.php): Only UI, validation rules, field definitions
- **Validation Layer** (PrepareCheckInValidator): Room availability, capacity, date overlap checks
- **Business Logic Layer** (CheckInService): Hold creation, payment processing, state transitions
- **Presentation Layer** (Livewire components): Real-time updates, live pricing, capacity indicators

---

## 7. TESTING GAPS

Current implementation lacks tests for:
- Pre-population edge cases (deleted rooms, inactive rooms, date mismatches)
- Primary guest exclusivity enforcement
- Capacity validation
- Race conditions (two staff preparing same room simultaneously)
- Hold expiration and cleanup
- Pricing calculation accuracy

**Recommendation:** Add integration tests covering:
```php
test_prepare_checkin_skips_deleted_held_rooms()
test_prepare_checkin_enforces_primary_guest_exclusivity()
test_prepare_checkin_validates_room_capacity()
test_prepare_checkin_prevents_overbooking()
test_prepare_checkin_calculates_pricing_correctly()
test_prepare_checkin_expires_holds_automatically()
```

---

## 8. FINAL VERDICT

**Overall Assessment:** ⭐⭐⭐⭐☆ (4/5)

The "Prepare Check-in" flow is **architecturally sound** with excellent business logic and audit trails. However, it suffers from:
- **Edge case handling gaps** (deleted rooms, date mismatches)
- **Performance issues at scale** (N+1 queries)
- **User experience friction** (no feedback, confusing toggles)

**Is it production-ready?** Yes, but with caveats:
- ✅ Core functionality works correctly
- ✅ Data integrity is maintained via transactions
- ✅ Audit trail is complete
- ⚠️ May confuse staff in edge cases (deleted rooms)
- ⚠️ Performance degrades with 100+ rooms
- ⚠️ Race conditions possible under high load

**Priority:** Implement "Immediate Fixes" before high-volume periods (e.g., semester start). Schedule "Short-term Enhancements" for next sprint.

---

## 9. NEXT STEPS

1. **Review with team** — Discuss which improvements align with roadmap
2. **Prioritize fixes** — Based on frequency of edge cases encountered
3. **Create tickets** — Break down improvements into actionable tasks
4. **Add monitoring** — Track how often pre-population fails silently
5. **Gather user feedback** — Ask staff about pain points during check-in

---

**Prepared by:** GitHub Copilot  
**Review Status:** Complete  
**Recommendation:** Proceed with targeted improvements while maintaining current functionality
