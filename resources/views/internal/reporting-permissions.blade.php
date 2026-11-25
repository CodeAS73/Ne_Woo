<fieldset>
    <legend>Supervisor reporting permissions</legend>
    <input type="hidden" name="edit_reporting_permissions" value="1">
    @foreach(config('rbac.supervisor_reporting') as $permission => $label)
        <label class="check-option">
            <input type="checkbox" name="reporting_permissions[]" value="{{ $permission }}" @checked(in_array($permission, $reportingPermissions, true))>
            {{ $label }}
        </label>
    @endforeach
</fieldset>
