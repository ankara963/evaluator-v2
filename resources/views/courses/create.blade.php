<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Create Course</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @include('courses.partials.styles')
    </head>
    <body>
        <main class="page">
            <section class="panel">
                <div class="header-row">
                    <div>
                        <h1>Create Course</h1>
                        <p>Add a course and define which existing courses must be passed before it can be taken.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('courses.store') }}">
                    @csrf
                    @include('courses._form', ['buttonLabel' => 'Create Course'])
                </form>
            </section>
        </main>
    </body>
</html>
