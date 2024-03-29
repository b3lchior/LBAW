@extends('layouts.app')

@section('title', 'Create Auction')

@section('content')
<div class="createAuctionForm">
    <form method="POST" action="{{ route('createAuction') }}">
        {{ csrf_field() }}

        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        @if ($errors->has('name'))
            <span class="error">
            {{ $errors->first('name') }}
            </span>
        @endif

        <label for="description" >Description</label>
        <input id="description" type="text" name="description" required>
        @if ($errors->has('description'))
            <span class="error">
                {{ $errors->first('description') }}
            </span>
        @endif

        <label for="starting_price" >Starting Price</label>
        <input id="starting_price" type="number" name="starting_price" required min="1" step="1">
        @if ($errors->has('starting_price'))
            <span class="error">
                {{ $errors->first('starting_price') }}
            </span>
        @endif

        <label for="end_t" >Auction End Date</label>
        <input id="end_t" type="date" name="end_t" required min="{{ date('Y-m-d') }}">
        @if ($errors->has('end_t'))
            <span class="error">
                {{ $errors->first('end_t') }}
            </span>
        @endif
        <div class="final-buttons">
            <button type="submit">
                Create
            </button>
            <a class="button button-outline" href="{{ route('user', ['id'=> $id]) }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
