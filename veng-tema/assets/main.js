document.addEventListener('DOMContentLoaded', function () {
  var html = document.documentElement;

  var darkBtn = document.getElementById('veng-dark-toggle');
  if (darkBtn) {
    darkBtn.addEventListener('click', function () {
      var isDark = html.classList.toggle('dark');
      localStorage.setItem('vengDarkMode', String(isDark));
    });
  }

  var searchBtn = document.getElementById('veng-search-toggle');
  var searchBox = document.getElementById('veng-searchbox');
  if (searchBtn && searchBox) {
    searchBtn.addEventListener('click', function () {
      searchBox.style.display = searchBox.style.display === 'block' ? 'none' : 'block';
      var input = searchBox.querySelector('input');
      if (input && searchBox.style.display === 'block') input.focus();
    });
  }

  // Anket oylama
  document.querySelectorAll('.poll-widget').forEach(function (widget) {
    var pollId = widget.dataset.pollId;
    var voted = false;
    widget.querySelectorAll('.poll-option').forEach(function (btn, index) {
      btn.addEventListener('click', function () {
        if (voted) return;
        voted = true;
        var body = new URLSearchParams({
          action: 'veng_vote_poll',
          nonce: VengData.nonce,
          poll_id: pollId,
          option_index: index,
        });
        fetch(VengData.ajaxUrl, { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              var options = res.data.options;
              var total = options.reduce(function (s, o) { return s + parseInt(o.votes, 10); }, 0);
              widget.querySelectorAll('.poll-option').forEach(function (b, i) {
                var pct = total > 0 ? Math.round((options[i].votes / total) * 100) : 0;
                var fill = b.querySelector('.poll-option-fill');
                if (fill) fill.style.width = pct + '%';
                var pctEl = b.querySelector('.poll-pct');
                if (pctEl) pctEl.textContent = '%' + pct;
              });
            }
          });
      });
    });
  });

  // Bülten formu
  var newsletterForm = document.getElementById('veng-newsletter-form');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = newsletterForm.querySelector('input[name=email]').value;
      var msgEl = newsletterForm.querySelector('.form-msg');
      var body = new URLSearchParams({ action: 'veng_newsletter', nonce: VengData.nonce, email: email });
      fetch(VengData.ajaxUrl, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (msgEl) msgEl.textContent = res.success ? 'Kaydınız alındı.' : (res.data && res.data.message) || 'Hata oluştu.';
          if (res.success) newsletterForm.reset();
        });
    });
  }

  // İletişim formu
  var contactForm = document.getElementById('veng-contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = new FormData(contactForm);
      var body = new URLSearchParams({
        action: 'veng_contact',
        nonce: VengData.nonce,
        name: data.get('name'),
        email: data.get('email'),
        subject: data.get('subject'),
        message: data.get('message'),
      });
      var msgEl = contactForm.querySelector('.form-msg');
      fetch(VengData.ajaxUrl, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            contactForm.style.display = 'none';
            if (msgEl) msgEl.textContent = 'Mesajınız alındı, teşekkürler.';
          } else if (msgEl) {
            msgEl.textContent = (res.data && res.data.message) || 'Hata oluştu.';
          }
        });
    });
  }

  // Sonraki haber otomatik yükleme (infinite scroll)
  var feed = document.getElementById('veng-infinite-feed');
  if (feed) {
    var sentinel = document.getElementById('veng-infinite-sentinel');
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && feed.dataset.loading === '0') {
          loadNextArticle();
        }
      });
    }, { rootMargin: '400px' });
    observer.observe(sentinel);

    function loadNextArticle() {
      feed.dataset.loading = '1';
      sentinel.innerHTML = '<div class="veng-infinite-spinner"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>';
      var before = encodeURIComponent(feed.dataset.currentDate);
      fetch('/wp-json/wp/v2/posts?before=' + before + '&per_page=1&_embed')
        .then(function (r) { return r.json(); })
        .then(function (posts) {
          sentinel.querySelector('.veng-infinite-spinner')?.remove();
          if (!Array.isArray(posts) || posts.length === 0) {
            observer.disconnect();
            return;
          }
          var post = posts[0];
          var cat = post._embedded && post._embedded['wp:term'] && post._embedded['wp:term'][0] && post._embedded['wp:term'][0][0];
          var media = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0];
          var img = media && media.source_url ? '<div class="article-cover"><img src="' + media.source_url + '" alt=""></div>' : '';
          var badge = cat ? '<a class="badge" href="' + cat.link + '">' + cat.name + '</a>' : '';

          var block = document.createElement('article');
          block.className = 'veng-loaded-article';
          block.innerHTML =
            '<div class="veng-next-divider"><span>Sonraki Haber</span></div>' +
            '<div class="article-header">' + badge +
            '<h1 class="article-title" style="font-size:24px;">' + post.title.rendered + '</h1></div>' +
            img +
            '<div class="article-content news-article-content">' + post.content.rendered + '</div>';

          block.querySelector('.article-title').addEventListener('click', function () {
            window.history.pushState({}, '', post.link);
            document.title = post.title.rendered.replace(/&#8217;/g, "’");
          });

          feed.insertBefore(block, sentinel);
          feed.dataset.currentDate = post.date;
          feed.dataset.loading = '0';
        })
        .catch(function () {
          feed.dataset.loading = '0';
        });
    }
  }

  // Bağlantı kopyala
  document.querySelectorAll('.copy-link-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(window.location.href).then(function () {
        var original = btn.textContent;
        btn.textContent = '✓';
        setTimeout(function () { btn.textContent = original; }, 1500);
      });
    });
  });

  // "Bugün Ne Oldu" özetini anlık yeniden üret.
  var digestBtn = document.getElementById('veng-hourly-digest-refresh');
  if (digestBtn) {
    digestBtn.addEventListener('click', function () {
      digestBtn.disabled = true;
      var original = digestBtn.textContent;
      digestBtn.textContent = 'Taranıyor…';

      var body = new URLSearchParams();
      body.set('action', 'veng_hourly_digest_refresh');
      body.set('nonce', VengData.nonce);

      fetch(VengData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            var list = document.getElementById('veng-hourly-digest-list');
            if (list) { list.outerHTML = res.data.html; } else { document.getElementById('veng-hourly-digest-time').insertAdjacentHTML('beforebegin', res.data.html); }
            document.getElementById('veng-hourly-digest-time').textContent = res.data.time;
          }
        })
        .finally(function () {
          digestBtn.disabled = false;
          digestBtn.textContent = original;
        });
    });
  }

  // Özel gün afiş sliderı: 4 saniyede bir sıradaki görsele geçer.
  var peaceBanner = document.getElementById('veng-peace-banner');
  if (peaceBanner) {
    var peaceSlides = peaceBanner.querySelectorAll('.peace-day-slide');
    var peaceIndex = 0;
    if (peaceSlides.length > 1) {
      setInterval(function () {
        peaceSlides[peaceIndex].classList.remove('active');
        peaceIndex = (peaceIndex + 1) % peaceSlides.length;
        peaceSlides[peaceIndex].classList.add('active');
      }, 4000);
    }
  }

  // Başa dön butonu: belli bir kaydırma miktarından sonra görünür, tıklayınca yumuşak kaydırma.
  var backToTop = document.getElementById('veng-back-to-top');
  if (backToTop) {
    var toggleBackToTop = function () {
      backToTop.classList.toggle('visible', window.scrollY > 600);
    };
    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    toggleBackToTop();
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
});
