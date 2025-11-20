(function () {
  const DATA_URL = 'data/site-data.json';
  const PLACEHOLDER_IMAGE =
    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NDAiIGhlaWdodD0iNDAwIiByb2xlPSJpbWciIGFyaWEtbGFiZWw9IlNlbSBpbWFnZW0iPjxyZWN0IHdpZHRoPSI2NDAiIGhlaWdodD0iNDAwIiBmaWxsPSIjZjVmN2ZhIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGR5PSIwLjMuZW0iIHRleHQtYW5jaG9yPSJtaWRkbGUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIGZpbGw9IiNiM2IwYjciIGZvbnQtc2l6ZT0iMjQiIGZvbnQtZmFtaWx5PSdJbnRlcmZhY2UsIHNhbnMtc2VyaWYnPiBTZW0gaW1hZ2VtIDwvdGV4dD48L3N2Zz4=';

  function init() {
    injectStyles();
    loadSiteData()
      .then((data) => {
        if (!data) return;
        renderStats(data.stats || {});
        renderHomeBlog(data.blogs || []);
        renderBlogList(data.blogs || []);
        renderSingleBlog(data.blogs || []);
        renderFooterBlog(data.blogs || []);
        renderSidebarBlog(data.blogs || []);
      })
      .catch((error) => {
        console.error(error);
        showHomeBlogEmpty('Não foi possível carregar os artigos neste momento.', true);
        showBlogListEmpty('Não foi possível carregar os artigos neste momento.', true);
      });
  }

  function loadSiteData() {
    const url = `${DATA_URL}?v=${Date.now()}`;

    if (typeof window.fetch === 'function') {
      return fetch(url, { cache: 'no-store' }).then((response) => {
        if (!response.ok) {
          throw new Error('Não foi possível carregar os dados do site.');
        }
        return response.json();
      });
    }

    return new Promise((resolve, reject) => {
      const request = new XMLHttpRequest();
      request.open('GET', url, true);
      request.responseType = 'json';
      request.onload = function () {
        if (request.status >= 200 && request.status < 300) {
          try {
            const payload = request.response || JSON.parse(request.responseText || 'null');
            resolve(payload);
          } catch (parseError) {
            reject(new Error('Não foi possível interpretar os dados do site.'));
          }
        } else {
          reject(new Error('Não foi possível carregar os dados do site.'));
        }
      };
      request.onerror = function () {
        reject(new Error('Não foi possível carregar os dados do site.'));
      };
      request.send();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function renderStats(stats) {
    if (!stats) return;
    const formatter = new Intl.NumberFormat('pt-PT');
    document.querySelectorAll('[data-stat]').forEach((element) => {
      const key = element.getAttribute('data-stat');
      const value = typeof stats[key] !== 'undefined' ? Number(stats[key]) : null;
      if (value === null || Number.isNaN(value)) return;
      element.setAttribute('data-number', String(value));
      element.textContent = formatter.format(value);
    });
  }

  function renderHomeBlog(blogs) {
    const container = document.getElementById('home-blog-list');
    const emptyState = document.getElementById('home-blog-empty');
    if (!container) return;
    container.innerHTML = '';
    if (!blogs.length) {
      if (emptyState) {
        showHomeBlogEmpty('Nenhum artigo publicado no momento. Volte em breve!');
      }
      return;
    }
    blogs.slice(0, 3).forEach((post) => {
      container.appendChild(createBlogCard(post));
    });
    if (emptyState) emptyState.style.display = 'none';
  }

  function renderBlogList(blogs) {
    const list = document.getElementById('blog-list');
    const empty = document.getElementById('blog-empty');
    if (!list) return;
    list.innerHTML = '';
    if (!blogs.length) {
      if (empty) {
        showBlogListEmpty('Nenhum artigo publicado no momento. Volte em breve!');
      }
      return;
    }
    blogs.forEach((post) => {
      list.appendChild(createBlogCard(post, { showExcerpt: true }));
    });
    if (empty) empty.style.display = 'none';
  }

  function renderSingleBlog(blogs) {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');
    const singleContainer = document.getElementById('single-blog');
    const titleEl = document.getElementById('single-title');
    const metaEl = document.getElementById('single-meta');
    const contentEl = document.getElementById('single-content');
    const heroTitle = document.getElementById('single-hero-title');
    const breadcrumbTitle = document.getElementById('single-breadcrumb-title');
    const imageWrapper = document.getElementById('single-image-wrapper');
    const imageEl = document.getElementById('single-image');
    const notFound = document.getElementById('single-not-found');

    if (!singleContainer || !titleEl || !metaEl || !contentEl) return;

    const post = blogs.find((item) => item.slug === slug) || null;
    if (!post) {
      if (notFound) notFound.style.display = 'block';
      singleContainer.style.display = 'none';
      if (heroTitle) heroTitle.textContent = 'Blog';
      if (breadcrumbTitle) breadcrumbTitle.textContent = 'Artigo não encontrado';
      return;
    }

    if (notFound) notFound.style.display = 'none';
    singleContainer.style.display = 'block';
    titleEl.textContent = post.title;
    if (heroTitle) heroTitle.textContent = post.title;
    if (breadcrumbTitle) breadcrumbTitle.textContent = post.title;
    metaEl.textContent = `${formatDate(post.date)} · ${post.author}`;

    if (imageEl) {
      imageEl.loading = 'lazy';
      applyImageFallback(imageEl);
    }

    if (post.image && imageEl && imageWrapper) {
      imageEl.src = post.image;
      imageEl.alt = post.title;
      imageWrapper.style.display = 'block';
    } else if (imageEl && imageWrapper) {
      imageEl.src = PLACEHOLDER_IMAGE;
      imageEl.alt = post.title;
      imageWrapper.style.display = 'block';
    } else if (imageWrapper) {
      imageWrapper.style.display = 'none';
    }

    contentEl.innerHTML = formatContent(post.content);
  }

  function showHomeBlogEmpty(message, isError) {
    const emptyState = document.getElementById('home-blog-empty');
    if (!emptyState) return;
    const textElement = emptyState.querySelector('p') || emptyState;
    if (textElement.classList) {
      textElement.classList.toggle('text-danger', Boolean(isError));
      textElement.classList.toggle('text-muted', !isError);
    }
    textElement.textContent = message;
    emptyState.style.display = 'block';
  }

  function showBlogListEmpty(message, isError) {
    const empty = document.getElementById('blog-empty');
    if (!empty) return;
    const textElement = empty.querySelector('p') || empty;
    if (textElement.classList) {
      textElement.classList.toggle('text-danger', Boolean(isError));
      textElement.classList.toggle('text-muted', !isError);
    }
    textElement.textContent = message;
    empty.style.display = 'block';
  }

  function renderFooterBlog(blogs) {
    const footerContainer = document.getElementById('footer-blog-list');
    if (!footerContainer) return;
    footerContainer.innerHTML = '';
    blogs.slice(0, 2).forEach((post) => {
      footerContainer.appendChild(createFooterItem(post));
    });
  }

  function renderSidebarBlog(blogs) {
    const sidebarContainer = document.getElementById('sidebar-blog-list');
    if (!sidebarContainer) return;
    sidebarContainer.innerHTML = '';
    blogs.slice(0, 3).forEach((post) => {
      sidebarContainer.appendChild(createSidebarItem(post));
    });
  }

  function createBlogCard(post, options) {
    const showExcerpt = !options || options.showExcerpt !== false;
    const col = document.createElement('div');
    col.className = 'col-md-6 col-lg-4 ftco-animate d-flex';

    const card = document.createElement('article');
    card.className = 'blog-card w-100 d-flex flex-column';

    const imageSection = document.createElement('div');
    imageSection.className = 'blog-card-figure position-relative';

    const imageLink = document.createElement('a');
    imageLink.className = 'blog-card-image d-block';
    imageLink.href = `blog-single.html?slug=${encodeURIComponent(post.slug)}`;

    const img = document.createElement('img');
    img.src = getImageSource(post);
    img.alt = post.title || 'Imagem do artigo';
    img.loading = 'lazy';
    applyImageFallback(img);
    imageLink.appendChild(img);

    const dateParts = getDateParts(post.date);
    const dateBadge = document.createElement('div');
    dateBadge.className = 'blog-card-date text-center';
    const daySpan = document.createElement('span');
    daySpan.className = 'day';
    daySpan.textContent = dateParts.day;
    const monthSpan = document.createElement('span');
    monthSpan.className = 'month';
    monthSpan.textContent = dateParts.month;
    const yearSpan = document.createElement('span');
    yearSpan.className = 'year';
    yearSpan.textContent = dateParts.year;
    dateBadge.appendChild(daySpan);
    dateBadge.appendChild(monthSpan);
    dateBadge.appendChild(yearSpan);

    imageSection.appendChild(imageLink);
    imageSection.appendChild(dateBadge);

    const body = document.createElement('div');
    body.className = 'blog-card-body bg-white p-4 d-flex flex-column flex-grow-1';

    const heading = document.createElement('h3');
    heading.className = 'heading mb-3';
    const headingLink = document.createElement('a');
    headingLink.href = imageLink.href;
    headingLink.textContent = post.title;
    heading.appendChild(headingLink);
    body.appendChild(heading);

    if (showExcerpt && post.excerpt) {
      const paragraph = document.createElement('p');
      paragraph.className = 'blog-card-excerpt flex-grow-1';
      paragraph.textContent = post.excerpt;
      body.appendChild(paragraph);
    }

    const footer = document.createElement('div');
    footer.className = 'blog-card-footer d-flex align-items-center justify-content-between mt-4 pt-2';

    const moreLink = document.createElement('a');
    moreLink.className = 'btn btn-primary';
    moreLink.href = imageLink.href;
    moreLink.innerHTML = 'Saber Mais<span class="ion-ios-arrow-round-forward"></span>';

    const metaInfo = document.createElement('div');
    metaInfo.className = 'blog-card-meta text-right';
    const authorSpan = document.createElement('span');
    authorSpan.className = 'author d-block';
    authorSpan.textContent = post.author || '';
    const dateSpan = document.createElement('span');
    dateSpan.className = 'date text-muted';
    dateSpan.textContent = formatDate(post.date);
    metaInfo.appendChild(authorSpan);
    metaInfo.appendChild(dateSpan);

    footer.appendChild(moreLink);
    footer.appendChild(metaInfo);

    body.appendChild(footer);

    card.appendChild(imageSection);
    card.appendChild(body);
    col.appendChild(card);

    return col;
  }

  function createFooterItem(post) {
    const wrapper = document.createElement('div');
    wrapper.className = 'block-21 mb-4 d-flex';

    const imageLink = document.createElement('a');
    imageLink.className = 'blog-img mr-4 d-inline-block overflow-hidden rounded';
    imageLink.href = `blog-single.html?slug=${encodeURIComponent(post.slug)}`;

    const img = document.createElement('img');
    img.className = 'footer-blog-thumb';
    img.src = getImageSource(post);
    img.alt = post.title || 'Imagem do artigo';
    img.loading = 'lazy';
    applyImageFallback(img);
    imageLink.appendChild(img);

    const text = document.createElement('div');
    text.className = 'text';

    const heading = document.createElement('h3');
    heading.className = 'heading';
    const link = document.createElement('a');
    link.href = imageLink.href;
    link.textContent = post.title;
    heading.appendChild(link);

    const meta = document.createElement('div');
    meta.className = 'meta';
    meta.innerHTML = `
      <div><a href="${link.href}"><span class="icon-calendar"></span> ${formatDate(post.date)}</a></div>
      <div><a href="${link.href}"><span class="icon-person"></span> ${post.author}</a></div>
    `;

    text.appendChild(heading);
    text.appendChild(meta);

    wrapper.appendChild(imageLink);
    wrapper.appendChild(text);
    return wrapper;
  }

  function createSidebarItem(post) {
    const wrapper = document.createElement('div');
    wrapper.className = 'block-21 mb-4 d-flex';

    const imageLink = document.createElement('a');
    imageLink.className = 'blog-img mr-4 d-inline-block overflow-hidden rounded';
    imageLink.href = `blog-single.html?slug=${encodeURIComponent(post.slug)}`;

    const img = document.createElement('img');
    img.className = 'sidebar-blog-thumb';
    img.src = getImageSource(post);
    img.alt = post.title || 'Imagem do artigo';
    img.loading = 'lazy';
    applyImageFallback(img);
    imageLink.appendChild(img);

    const text = document.createElement('div');
    text.className = 'text';

    const heading = document.createElement('h3');
    heading.className = 'heading';
    const link = document.createElement('a');
    link.href = imageLink.href;
    link.textContent = post.title;
    heading.appendChild(link);

    const meta = document.createElement('div');
    meta.className = 'meta';
    meta.innerHTML = `
      <div><a href="${link.href}"><span class="icon-calendar"></span> ${formatDate(post.date)}</a></div>
      <div><a href="${link.href}"><span class="icon-person"></span> ${post.author}</a></div>
    `;

    text.appendChild(heading);
    text.appendChild(meta);

    wrapper.appendChild(imageLink);
    wrapper.appendChild(text);
    return wrapper;
  }

  function injectStyles() {
    if (document.getElementById('site-data-dynamic-styles')) return;
    const style = document.createElement('style');
    style.id = 'site-data-dynamic-styles';
    style.textContent = `
      .blog-card { border-radius: 0.75rem; overflow: hidden; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06); background: #fff; transition: transform 0.3s ease, box-shadow 0.3s ease; }
      .blog-card:hover { transform: translateY(-6px); box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1); }
      .blog-card-figure { position: relative; background: #f8f9fa; overflow: hidden; }
      .blog-card-image { position: relative; display: block; overflow: hidden; }
      .blog-card-image::before { content: ''; display: block; padding-top: 62.5%; }
      .blog-card-image img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
      .blog-card:hover .blog-card-image img { transform: scale(1.05); }
      .blog-card-date { position: absolute; top: 1rem; left: 1rem; background: #1d62f0; color: #fff; border-radius: 0.75rem; padding: 0.5rem 0.85rem; line-height: 1.1; text-transform: uppercase; font-weight: 700; box-shadow: 0 8px 20px rgba(29, 98, 240, 0.3); }
      .blog-card-date .day { display: block; font-size: 1.8rem; }
      .blog-card-date .month { display: block; font-size: 0.95rem; letter-spacing: 0.08em; }
      .blog-card-date .year { display: block; font-size: 0.75rem; opacity: 0.85; }
      .blog-card-body { min-height: 260px; }
      .blog-card-excerpt { color: #6c757d; margin-bottom: 0; }
      .blog-card-footer .btn { padding-inline: 1.5rem; border-radius: 999px; font-weight: 600; }
      .blog-card-meta .author { font-weight: 700; color: #343a40; }
      .blog-card-meta .date { font-size: 0.85rem; }
      .footer-blog-thumb, .sidebar-blog-thumb { width: 80px; height: 80px; object-fit: cover; }
      @media (max-width: 991.98px) {
        .blog-card-body { min-height: auto; }
      }
      @media (max-width: 767.98px) {
        .footer-blog-thumb, .sidebar-blog-thumb { width: 64px; height: 64px; }
      }
    `;
    document.head.appendChild(style);
  }

  function getImageSource(post) {
    if (post && typeof post.image === 'string' && post.image.trim() !== '') {
      return post.image.trim();
    }
    return PLACEHOLDER_IMAGE;
  }

  function applyImageFallback(imageElement) {
    if (!imageElement || typeof imageElement.addEventListener !== 'function') return;
    imageElement.addEventListener('error', function handleError() {
      if (imageElement.dataset && imageElement.dataset.fallbackApplied === 'true') return;
      if (imageElement.dataset) {
        imageElement.dataset.fallbackApplied = 'true';
      }
      imageElement.src = PLACEHOLDER_IMAGE;
    });
  }

  function getDateParts(dateString) {
    const date = new Date(dateString || '');
    if (Number.isNaN(date.getTime())) {
      return { day: '--', month: '--', year: '' };
    }
    const day = String(date.getDate()).padStart(2, '0');
    const rawMonth = date.toLocaleString('pt-PT', { month: 'short' }) || '';
    const month = capitalize(rawMonth.replace('.', '').trim());
    const year = String(date.getFullYear());
    return { day, month, year };
  }

  function formatDate(dateString) {
    const date = new Date(dateString || '');
    if (Number.isNaN(date.getTime())) {
      return dateString || '';
    }
    const day = String(date.getDate()).padStart(2, '0');
    const rawMonth = date.toLocaleString('pt-PT', { month: 'short' }) || '';
    const month = capitalize(rawMonth.replace('.', '').trim());
    const year = String(date.getFullYear());
    return `${day} ${month} ${year}`.trim();
  }

  function formatContent(text) {
    if (!text) return '';
    return text
      .split(/\n\s*\n/)
      .map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
      .join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
  }
})();
