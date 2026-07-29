<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} API</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --e50: #f0fdf4; --e100: #dcfce7; --e200: #bbf7d0; --e300: #86efac;
            --e400: #34d399; --e500: #10b981; --e600: #059669; --e700: #047857;
            --s50: #f8fafc; --s100: #f1f5f9; --s200: #e2e8f0; --s300: #cbd5e1;
            --s400: #94a3b8; --s500: #64748b; --s600: #475569; --s700: #334155; --s900: #0f172a;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--s50); color: var(--s900); overflow: hidden; height: 100vh; }


        /* HERO */
        .hero { position: relative; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px; overflow: hidden; }
        .hero-c { position: relative; z-index: 10; text-align: center; max-width: 700px; }
        .hero h1 { font-size: clamp(40px, 6vw, 64px); font-weight: 800; line-height: 1.1; letter-spacing: -0.03em; margin-bottom: 20px; animation: fadeUp 0.7s ease-out 0.1s both; }
        .grad { background: linear-gradient(135deg, var(--e500), var(--e700)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero-sub { font-size: 18px; color: var(--s500); max-width: 520px; margin: 0 auto 36px; animation: fadeUp 0.7s ease-out 0.2s both; }
        .hero-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; animation: fadeUp 0.7s ease-out 0.3s both; }
        .hero-ftr { position: absolute; bottom: 32px; left: 0; right: 0; text-align: center; animation: fadeUp 0.7s ease-out 0.4s both; }
        .hero-ftr-label { font-size: 11px; color: var(--s400); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .hero-ftr-name { font-size: 15px; font-weight: 600; color: var(--e600); text-decoration: none; transition: color 0.2s; }
        .hero-ftr-name:hover { color: var(--e700); }
        .hero-ftr-contact { display: flex; justify-content: center; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
        .hero-ftr-item { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--s500); text-decoration: none; transition: color 0.2s; }
        .hero-ftr-item svg { width: 14px; height: 14px; }
        .hero-ftr-item:hover { color: var(--e600); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; border-radius: 12px; font-size: 15px; font-weight: 600; text-decoration: none; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-p { background: linear-gradient(135deg, var(--e500), var(--e600)); color: white; box-shadow: 0 4px 14px rgba(16,185,129,0.35); }
        .btn-p:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.45); }
        .btn-o { background: white; color: var(--s700); border: 1px solid var(--s200); }
        .btn-o:hover { border-color: var(--e300); color: var(--e600); transform: translateY(-2px); }
        .btn svg { width: 18px; height: 18px; }

        /* NETWORK */
        .net { position: absolute; inset: 0; overflow: hidden; z-index: 1; }
        .net::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 50%, rgba(16,185,129,0.06) 0%, transparent 70%); }
        .nd { position: absolute; border-radius: 50%; background: radial-gradient(circle, rgba(16,185,129,0.25) 0%, rgba(16,185,129,0.05) 70%); animation: float 6s ease-in-out infinite; }
        .nd::after { content: ''; position: absolute; inset: 25%; border-radius: 50%; background: rgba(16,185,129,0.4); }
        .nd1 { width: 200px; height: 200px; top: 10%; left: 5%; }
        .nd2 { width: 140px; height: 140px; top: 20%; right: 10%; animation-delay: -1.5s; }
        .nd3 { width: 100px; height: 100px; bottom: 20%; left: 15%; animation-delay: -3s; }
        .nd4 { width: 160px; height: 160px; bottom: 15%; right: 15%; animation-delay: -4.5s; }
        .nd5 { width: 80px; height: 80px; top: 50%; left: 50%; animation-delay: -2s; }
        .nd6 { width: 120px; height: 120px; top: 40%; left: 25%; animation-delay: -3.5s; }
        .nd7 { width: 90px; height: 90px; top: 35%; right: 25%; animation-delay: -1s; }
        .ln { position: absolute; height: 1px; background: linear-gradient(90deg, transparent, rgba(16,185,129,0.15), transparent); animation: lineFlow 4s ease-in-out infinite; }
        .ln1 { width: 300px; top: 25%; left: 10%; transform: rotate(15deg); }
        .ln2 { width: 250px; top: 45%; right: 5%; transform: rotate(-10deg); animation-delay: -1s; }
        .ln3 { width: 350px; bottom: 30%; left: 20%; transform: rotate(5deg); animation-delay: -2s; }
        .ln4 { width: 200px; top: 60%; left: 40%; transform: rotate(-20deg); animation-delay: -3s; }
        .ln5 { width: 280px; top: 30%; right: 20%; transform: rotate(25deg); animation-delay: -1.5s; }
        .pt { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: var(--e400); animation: particleMove 8s linear infinite; opacity: 0; }
        .pt1 { top: 20%; } .pt2 { top: 50%; animation-delay: -2s; } .pt3 { top: 70%; animation-delay: -4s; } .pt4 { top: 35%; animation-delay: -6s; }


        /* ENDPOINTS */
        .eps { padding: 80px 32px; max-width: 1100px; margin: 0 auto; }
        .sh { text-align: center; margin-bottom: 48px; }
        .sh h2 { font-size: 32px; font-weight: 800; letter-spacing: -0.025em; margin-bottom: 12px; }
        .sh p { font-size: 16px; color: var(--s500); max-width: 500px; margin: 0 auto; }
        .eg { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .ec { background: white; border: 1px solid var(--s200); border-radius: 16px; padding: 24px; transition: all 0.3s; }
        .ec:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); border-color: var(--e200); }
        .eh { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .ei { width: 40px; height: 40px; border-radius: 10px; background: var(--e50); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ei svg { width: 18px; height: 18px; color: var(--e600); }
        .eh h3 { font-size: 15px; font-weight: 700; }
        .ep-list { list-style: none; }
        .ep-list li { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--s100); font-size: 13px; }
        .ep-list li:last-child { border-bottom: none; }
        .method { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; min-width: 42px; text-align: center; }
        .m-get { background: #dbeafe; color: #1d4ed8; }
        .m-post { background: #dcfce7; color: #16a34a; }
        .m-patch { background: #fef3c7; color: #d97706; }
        .m-delete { background: #fee2e2; color: #dc2626; }
        .ep-path { color: var(--s600); font-family: monospace; font-size: 12px; }

        /* ANIMATIONS */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes float { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-20px) scale(1.05); } }
        @keyframes lineFlow { 0%, 100% { opacity: 0.3; } 50% { opacity: 0.8; } }
        @keyframes particleMove { 0% { transform: translateX(0); opacity: 0; } 10% { opacity: 0.8; } 90% { opacity: 0.8; } 100% { transform: translateX(100vw); opacity: 0; } }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sg { grid-template-columns: repeat(2, 1fr); }
            .eg { grid-template-columns: 1fr; }
            .hdr-links { display: none; }
        }
        @media (max-width: 480px) {
            .sg { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- HERO -->
<section class="hero">
    <div class="net">
        <div class="nd nd1"></div><div class="nd nd2"></div><div class="nd nd3"></div>
        <div class="nd nd4"></div><div class="nd nd5"></div><div class="nd nd6"></div><div class="nd nd7"></div>
        <div class="ln ln1"></div><div class="ln ln2"></div><div class="ln ln3"></div>
        <div class="ln ln4"></div><div class="ln ln5"></div>
        <div class="pt pt1"></div><div class="pt pt2"></div><div class="pt pt3"></div><div class="pt pt4"></div>
    </div>
    <div class="hero-c">
        <h1><span class="grad">{{ config('app.name') }}</span><br>REST API</h1>
        <p class="hero-sub">A powerful RESTful API for financial management. Handle sales, payments, expenses, and more with secure JWT authentication.</p>
        <div class="hero-btns">
            <a href="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/login" class="btn btn-p" target="_blank">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
                Open App
            </a>
        </div>
    </div>
    <div class="hero-ftr">
        <p class="hero-ftr-label">Designed & Developed by</p>
        <a href="https://www.linkedin.com/in/mohamed-insath-90a40724a" target="_blank" class="hero-ftr-name">Mohamed Insath</a>
        <div class="hero-ftr-contact">
            <a href="tel:+94750552243" class="hero-ftr-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                +94 750 552 243
            </a>
            <a href="https://wa.me/94750552243" target="_blank" class="hero-ftr-item">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
            </a>
        </div>
    </div>
</section>


</body>
</html>
