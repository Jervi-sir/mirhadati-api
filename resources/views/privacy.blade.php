<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Privacy Policy - Mirhadati</title>

  {{-- Tailwind CDN --}}
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink: '#0D0E0C',
            paper: '#FAF8F5',
            primary: '#007B7A',
          }
        }
      }
    }
  </script>
</head>
<body class="bg-paper text-ink antialiased">

  {{-- HEADER --}}
  <header class="border-b border-ink/10 bg-white">
    <div class="max-w-5xl mx-auto px-6 py-8">
      <a href="{{ url('/') }}" class="text-sm text-ink/60 hover:text-primary">&larr; Back</a>
      <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Privacy Policy</h1>
      <p class="mt-1 text-sm text-ink/60">
        Last updated: {{ now()->toFormattedDateString() }}
      </p>
      <button onclick="window.print()"
        class="mt-4 inline-flex items-center gap-2 rounded-lg border border-ink/15 bg-white px-4 py-1.5 text-sm font-semibold text-ink shadow-sm hover:bg-ink/5">
        🖨 Print
      </button>
    </div>
  </header>

  {{-- MAIN --}}
  <main class="max-w-5xl mx-auto px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

      {{-- SIDEBAR (TOC) --}}
      <aside class="lg:col-span-4">
        <div class="sticky top-6 rounded-xl border border-ink/10 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-semibold uppercase text-ink/60 mb-3">Contents</h2>
          <nav class="space-y-2 text-sm">
            <a href="#collect" class="block hover:underline">1. Information We Collect</a>
            <a href="#use" class="block hover:underline">2. How We Use Information</a>
            <a href="#share" class="block hover:underline">3. Sharing</a>
            <a href="#security" class="block hover:underline">4. Security</a>
            <a href="#rights" class="block hover:underline">5. Your Rights</a>
          </nav>
        </div>
      </aside>

      {{-- CONTENT --}}
      <section class="lg:col-span-8 leading-relaxed">
        <p class="mb-6">
          This Privacy Policy explains how <strong>{{ config('app.name') }}</strong> (“we”, “us”, or “our”)
          collects, uses, and protects your personal information when you use our website or mobile app.
        </p>

        <h2 id="collect" class="text-xl font-bold mt-8 mb-3">1. Information We Collect</h2>
        <ul class="list-disc pl-6 space-y-2">
          <li><strong>Account Data:</strong> such as your name, email, and login details.</li>
          <li><strong>Usage Data:</strong> like pages visited, device info, and location.</li>
          <li><strong>Content:</strong> any submissions or uploads you make.</li>
        </ul>

        <h2 id="use" class="text-xl font-bold mt-8 mb-3">2. How We Use Information</h2>
        <p>We use your information to:</p>
        <ul class="list-disc pl-6 space-y-2">
          <li>Provide, maintain, and improve our services.</li>
          <li>Personalize content and communicate updates.</li>
          <li>Protect against fraud and ensure legal compliance.</li>
        </ul>

        <h2 id="share" class="text-xl font-bold mt-8 mb-3">3. Sharing</h2>
        <p>
          We may share data with trusted service providers, legal authorities (if required),
          or during business transfers. We do <strong>not sell</strong> your personal data.
        </p>

        <h2 id="security" class="text-xl font-bold mt-8 mb-3">4. Security</h2>
        <p>
          We use reasonable administrative and technical safeguards to protect your data.
          However, no method of transmission over the internet is 100% secure.
        </p>

        <h2 id="rights" class="text-xl font-bold mt-8 mb-3">5. Your Rights</h2>
        <p>
          You can request access, correction, or deletion of your data.
          To exercise these rights, please contact us below.
        </p>

      </section>

    </div>
  </main>

  {{-- FOOTER --}}
  <footer class="border-t border-ink/10 text-center text-sm py-8 text-ink/60">
    &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
  </footer>
</body>
</html>
