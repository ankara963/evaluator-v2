<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Edit {{ $course->code }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @include('courses.partials.styles')
    </head>
    <body>
        <main class="page">
            <section class="panel">
                <div class="header-row">
                    <div>
                        <h1>Edit {{ $course->code }}</h1>
                        <p>Update the course definition and change which subjects must already be passed before this one can be taken.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('courses.update', $course) }}">
                    @csrf
                    @method('PUT')
                    @include('courses._form', ['buttonLabel' => 'Update Course'])
                </form>
            </section>
        </main>
    </body>
</html>
