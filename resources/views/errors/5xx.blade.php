@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', 'Something needs our attention')
@section('message', 'We could not complete that request. The issue has been recorded, and you can safely return to the homepage or let us know what happened.')
