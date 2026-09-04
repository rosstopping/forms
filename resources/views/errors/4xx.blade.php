@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', 'We could not open this page')
@section('message', 'The request could not be completed. Return to the homepage, or contact us if you need a hand finding the right place.')
