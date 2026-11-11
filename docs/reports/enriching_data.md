# Enriching report data (PHP)

Data can be added or altered before a report is rendered by implementing a processor.  
Extend `JeffreyBostoenExtensions\Reporting\Processor\Base` (which implements `iBase`) and override the relevant methods:

| Method         | Purpose                                                                  |
|----------------|--------------------------------------------------------------------------|
| `IsApplicable` | Whether the processor should run (by default, it does not).              |
| `BeforeFetch`  | Runs before the data is fetched. Useful to limit the attribute codes.    |
| `EnrichData`   | Runs after the main object set was converted. Add or alter data here.    |
| `DoExec`       | Produces output. Return `false` to skip the remaining processors.        |
| `GetRank`      | Order of execution (lower = first).                                      |

The report data is available through `Helper::GetData()`.


## Permissions

Report data is built from the OQL `filter` URL parameter, which is user input. The report generator therefore:

* verifies that the current user may read the class(es) selected by the filter, and only adds attachments if the user may read them.

Anything a processor fetches on its own is **not** covered by these checks. When a processor queries additional classes (for example in `EnrichData()`):

* Call `Helper::AssertReadAllowed($sClass)` first. It stops with a `SecurityException` unless the user may read the class.
* To skip silently instead, compare iTop's result strictly: `UserRights::IsActionAllowed($sClass, UR_ACTION_READ) === UR_ALLOWED_YES`. The method returns a tri-state value (`UR_ALLOWED_YES`, `UR_ALLOWED_NO`, `UR_ALLOWED_DEPENDS`), so a plain truthiness check is not sufficient.
* Use iTop's regular object sets (`DBObjectSet`, `CMDBObjectSet`) for business classes, so that the user's scopes keep being applied. Do not use raw SQL.
* Convert objects with `Helper::ConvertDBObjectToStdClass()` or `Helper::ConvertDBObjectSetToObjectResultArray()`.
* Do not copy request parameters into the data without validating them. Parameters should be read with `utils::ReadParam()` and an explicit sanitization filter.

Example:

```
public static function EnrichData() : void {

	// Skip silently if the user may not see these objects.
	if(UserRights::IsActionAllowed('Contract', UR_ACTION_READ) !== UR_ALLOWED_YES) {
		return;
	}

	$oSet = new DBObjectSet(DBObjectSearch::FromOQL('SELECT Contract WHERE org_id = :org_id', ['org_id' => $iOrgId]));
	Helper::GetData()->contracts = Helper::ConvertDBObjectSetToObjectResultArray($oSet);

}
```
