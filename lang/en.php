<?php

declare(strict_types=1);

/**
 * Message catalogue.
 */

return [
    'validation.required' => 'The :field field is required.',
    'validation.string' => 'The :field field must be text.',
    'validation.int' => 'The :field field must be a whole number.',
    'validation.numeric' => 'The :field field must be a number.',
    'validation.boolean' => 'The :field field must be true or false.',
    'validation.email' => 'The :field field must be a valid e-mail address.',
    'validation.url' => 'The :field field must be a valid URL.',
    'validation.min' => 'The :field field must be at least :min characters.',
    'validation.max' => 'The :field field may not be longer than :max characters.',
    'validation.regex' => 'The :field field has an invalid format.',
    'validation.in' => 'The :field field must be one of: :allowed.',
    'validation.confirmed' => 'The :field field does not match.',
    'validation.same' => 'The :field field does not match.',
    'validation.different' => 'The :field field must be different from :other.',
    'validation.slug' => 'The :field field may only contain lowercase letters, digits and dashes.',
    'validation.array' => 'The :field field must be a list.',
    'auth.failed' => 'Invalid credentials.',
    'auth.locked' => 'This account is temporarily locked after too many failed attempts. Please try again later.',
    'auth.rate_limited' => 'You are going too fast. Please wait :seconds seconds and try again.',
    'auth.csrf' => 'Your session has changed. Please reload the page and try again.',
    'error.404.title' => 'Page not found',
    'error.404.body' => 'This address does not exist, or the content has moved elsewhere.',
    'error.500.title' => 'Something went wrong',
    'error.500.body' => 'The server hit a problem while handling the request. Please try again later.',
    'error.403.title' => 'Access denied',
    'error.403.body' => 'Your account does not have permission to view this page.',
];
