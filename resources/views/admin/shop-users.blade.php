@extends('admin.shop-page')
@section('page_title', 'Users')
@section('shop_content')
<div class="card mb-4" id="shop-users"><div class="card-body"><h3>Shop users</h3><p>Owner: {{ $shop->owner->name }}. Admins manage settings and staff; managers manage products and sales.</p>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead><tbody>
    <tr><td>{{ $shop->owner->name }}</td><td>{{ $shop->owner->email }}</td><td>Owner</td><td></td></tr>
    @foreach($members as $member)<tr><td>{{ $member->user->name }}</td><td>{{ $member->user->email }}</td><td>{{ $member->role === 'admin' ? 'Shop admin' : 'Store manager' }}</td><td>
        <form method="post" action="{{ route('workspace.member.remove', [$shop, $member]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove access</button></form>
    </td></tr>@endforeach
</tbody></table></div>
</div></div>
<div class="card mb-4"><div class="card-body"><h3>Add or update shop user</h3>
@if($canManage)<form class="mt-3" method="post" action="{{ route('workspace.member', $shop) }}">@csrf<label class="form-label w-100">Name<input class="form-control" name="name" required value="{{ old('name') }}"></label><label class="form-label w-100">Email<input class="form-control" name="email" type="email" required value="{{ old('email') }}"></label><label class="form-label w-100">Initial password (new accounts only)<input class="form-control" name="password" type="password" minlength="12" autocomplete="new-password"></label><label class="form-label w-100">Role<select class="form-select" name="role"><option value="manager" @selected(old('role', 'manager') === 'manager')>Store manager</option><option value="admin" @selected(old('role') === 'admin')>Shop admin</option></select></label><p class="small text-muted">For a new account, enter an initial password of at least 12 characters. Existing users keep their current password. Enter an existing member email to update their shop role.</p><button class="btn btn-primary">Add or update shop user</button></form>@endif
</div></div>
@endsection
