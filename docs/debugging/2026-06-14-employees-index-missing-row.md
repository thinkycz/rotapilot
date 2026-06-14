# Dashboard vs. employees index — active employees count mismatch

## Symptom

- `http://rotapilot.test/dashboard` reports **`4` active employees**.
- `http://rotapilot.test/employees/index` lists only **3 employees** for the same manager.
- The user verified there really are 4 active employees; the index page is the one that's wrong.

## Evidence (reproduction)

Wrote a one-off script `scripts/debug-employees.php` and ran it against the local DB:

```
Manager: manager@example.com
Managed store IDs: [1,2,3,4]

Dashboard count (active + whereIn): 4
Index count (managedEmployeesQuery): 3
Index names: Anna Novak, Lucie Kralova, Tomas Dvorak

Index SQL: select * from `employee_profiles` where exists
    (select * from `stores` inner join `employee_store` on ...
        where `employee_profiles`.`id` = `employee_store`.`employee_profile_id`
        and `stores`.`id` = ?)
Index bindings: [1]                                  <-- smoking gun
```

`Total active in DB: 4` and `All in managed stores: 4` — both are 4 when filtered correctly. The 4 employees and their store assignments are:

```
[1] Anna Novak    (is_active=1) - stores: Downtown Cafe (1), Mall Kiosk (2)
[2] Pavel Svoboda (is_active=1) - stores: Mall Kiosk (2), Airport Outlet (3)
[3] Lucie Kralova (is_active=1) - stores: Downtown Cafe (1), Airport Outlet (3)
[4] Tomas Dvorak  (is_active=1) - stores: Downtown Cafe (1), Mall Kiosk (2)
```

Pavel is missing from the index because he is **not** assigned to store 1 — and the broken index query is filtering `stores.id = 1`, not `stores.id IN (1,2,3,4)`.

## Root cause

`app/Support/Authorization.php`, in `managedEmployeesQuery()` (line 176-178):

```php
return $builder->whereHas('stores', static function (Builder $q) use ($storeIds): void {
    $q->where('stores.id', $storeIds);   // <-- BUG
});
```

`Query\Builder::where()` with an array as the second argument does **not** auto-convert to `whereIn`. Laravel's `prepareValueAndOperator()` collapses the call to `where('stores.id', '=', $array)`, and when PDO binds the array it ends up coerced to its first scalar element. Net effect: the inner `EXISTS` is `stores.id = 1` instead of `stores.id IN (1,2,3,4)`, so any employee who isn't also assigned to store 1 is silently dropped.

The dashboard counts via `DashboardController::managerPayload()` and uses the **correct** call: `whereIn('stores.id', $storeIds)`. That is why it shows 4.

## Globalization check

- `where('col', $array)` in Laravel is a recurring footgun across this codebase. `grep` for `where(.*\[.*\]\.\.\.\\?|\$storeIds|\\\$ids\)\\)` should be done in a follow-up to make sure no other place has the same shape.
- `Authorization::managedEmployeesQuery()` is consumed by **two** pages (both affected):
    - `app/Http/Controllers/Web/Employees/EmployeeIndexController.php` (the page the user is looking at)
    - `app/Http/Controllers/Web/Availability/AvailabilityIndexController.php`
- No other consumer uses it.
- The dashboard has its own copy of the query and uses `whereIn` correctly — it is not affected.

## Fix

One-line change in `Authorization::managedEmployeesQuery()`:

```php
$q->whereIn('stores.id', $storeIds);
```

## Regression test

Add a Pest test in `tests/Feature/App/Support/AuthorizationTest.php` (new file) that:

- Creates a store manager who manages two stores.
- Creates three employees: A in store 1 only, B in store 2 only, C in both.
- Asserts `Authorization::managedEmployeesQuery($manager)->pluck('id')` returns all three ids.

This locks the contract and would have caught the original bug.

## Verification

- The debug script now reports `Index count: 4` and `Index names: Anna Novak, Lucie Kralova, Pavel Svoboda, Tomas Dvorak`.
- `make check` is green.
