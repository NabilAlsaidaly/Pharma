@csrf
<input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid7() }}">
<input type="hidden" name="expected_version" value="{{ (string) $version }}">
<input type="hidden" name="action" value="{{ $action }}">
