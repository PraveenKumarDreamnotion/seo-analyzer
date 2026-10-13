(function () {
  'use strict';

  var cfg = window.HaloAudit || {};
  var nonce = null;
  var STATUS = { pass: 'Pass', warn: 'Improve', fail: 'Fix', not_measured: 'Not measured' };

  function route(path) {
    var parts = path.split('?');
    var joiner = cfg.root.indexOf('?') === -1 ? '?' : '&';
    return cfg.root + parts[0] + (parts[1] ? joiner + parts[1] : '');
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined && text !== null) { node.textContent = String(text); }
    return node;
  }

  function clear(node) {
    while (node.firstChild) { node.removeChild(node.firstChild); }
  }

  function fetchNonce() {
    return fetch(route('nonce?t=' + Date.now()), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) { nonce = data.nonce; });
  }

  function send(method, path, body, retried) {
    var ready = nonce ? Promise.resolve() : fetchNonce();
    return ready.then(function () {
      return fetch(route(path), {
        method: method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-Halo-Nonce': nonce },
        body: body ? JSON.stringify(body) : undefined
      });
    }).then(function (response) {
      if (response.status === 403 && !retried && path.indexOf('report/') !== 0 && path.indexOf('scan') !== 0) {
        nonce = null;
        return send(method, path, body, true);
      }
      return response;
    });
  }

  function api(method, path, body) {
    return send(method, path, body, false).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) {
          var error = new Error(data.message || 'Something went wrong. Try again.');
          error.status = response.status;
          throw error;
        }
        return data;
      });
    });
  }

  function Widget(root) {
    this.root = root;
    this.form = root.querySelector('.halo-audit__form');
    this.status = root.querySelector('.halo-audit__status');
    this.results = root.querySelector('.halo-audit__results');
    this.button = root.querySelector('.halo-audit__button');
    this.data = null;
    this.form.addEventListener('submit', this.onSubmit.bind(this));
  }

  Widget.prototype.say = function (text, isError) {
    this.status.textContent = text || '';
    this.status.classList.toggle('halo-audit__status--error', !!isError);
  };

  Widget.prototype.onSubmit = function (event) {
    event.preventDefault();
    var url = this.form.elements.url.value.trim();
    var keyword = this.form.elements.keyword.value.trim();
    if (!url || !keyword) {
      this.say('Enter a page address and a keyword.', true);
      return;
    }
    var self = this;
    this.button.disabled = true;
    this.results.hidden = true;
    this.say('Reading the page. This takes up to 15 seconds.');

    api('POST', 'analyze', { url: url, keyword: keyword }).then(function (data) {
      self.data = data;
      self.say('');
      self.render();
      if (data.unlocked) { self.loadFull(); }
      return api('POST', 'speed', { report_id: data.report_id }).then(function (speed) {
        self.data.summary = speed.summary;
        self.data.speed_done = true;
        self.renderSummary();
        if (self.data.checks) { self.loadFull(); }
      }).catch(function () {
        self.data.speed_done = true;
        self.renderSummary();
      });
    }).catch(function (error) {
      self.say(error.message, true);
    }).then(function () {
      self.button.disabled = false;
    });
  };

  Widget.prototype.render = function () {
    clear(this.results);
    this.results.hidden = false;
    this.summaryBox = el('div', 'halo-audit__summary');
    this.actions = el('div', 'halo-audit__actions');
    this.gateBox = el('div', 'halo-audit__gate');
    this.findings = el('div', 'halo-audit__findings');
    this.results.appendChild(this.summaryBox);
    this.results.appendChild(this.actions);
    this.results.appendChild(this.gateBox);
    this.results.appendChild(this.findings);
    this.renderSummary();
    this.renderActions();
  };

  Widget.prototype.renderSummary = function () {
    var data = this.data;
    var summary = data.summary;
    clear(this.summaryBox);

    this.summaryBox.appendChild(el('p', 'halo-audit__url', data.url));
    var score = el('div', 'halo-audit__score');
    score.appendChild(el('span', 'halo-audit__score-number', summary.overall === null ? 'Not measured' : summary.overall));
    if (summary.grade) { score.appendChild(el('span', 'halo-audit__grade halo-audit__grade--' + summary.grade, 'Grade ' + summary.grade)); }
    this.summaryBox.appendChild(score);

    var list = el('ul', 'halo-audit__groups');
    Object.keys(summary.groups).forEach(function (key) {
      var group = summary.groups[key];
      var item = el('li', 'halo-audit__group');
      item.appendChild(el('span', 'halo-audit__group-name', data.labels[key] || key));
      var value;
      if (key === 'speed' && !data.speed_done) {
        value = 'Testing speed...';
      } else if (group.score === null) {
        value = 'Not measured';
      } else {
        value = group.score + ' / 100';
      }
      item.appendChild(el('span', 'halo-audit__group-score', value));
      item.appendChild(el('span', 'halo-audit__group-counts', group.pass + ' pass, ' + group.warn + ' to improve, ' + group.fail + ' to fix'));
      list.appendChild(item);
    });
    this.summaryBox.appendChild(list);
  };

  Widget.prototype.renderActions = function () {
    var self = this;
    clear(this.actions);
    var see = el('button', 'halo-audit__button', 'See all findings');
    see.type = 'button';
    see.addEventListener('click', function () { self.need(function () { self.loadFull(); }); });
    var pdf = el('button', 'halo-audit__button halo-audit__button--ghost', 'Download PDF');
    pdf.type = 'button';
    pdf.addEventListener('click', function () { self.need(function () { self.downloadPdf(); }); });
    this.actions.appendChild(see);
    this.actions.appendChild(pdf);
  };

  Widget.prototype.need = function (then) {
    if (this.data.unlocked) { then(); return; }
    this.renderGate(then);
  };

  Widget.prototype.renderGate = function (then) {
    var self = this;
    var box = this.gateBox;
    clear(box);
    box.appendChild(el('p', 'halo-audit__gate-intro', 'Enter your name and email. We send a 6-digit code to confirm the address.'));

    var name = el('input'); name.type = 'text'; name.placeholder = 'Your name'; name.autocomplete = 'name';
    var email = el('input'); email.type = 'email'; email.placeholder = 'Work email'; email.autocomplete = 'email';
    var sendButton = el('button', 'halo-audit__button', 'Send code'); sendButton.type = 'button';
    var code = el('input'); code.type = 'text'; code.inputMode = 'numeric'; code.maxLength = 6; code.placeholder = '6-digit code'; code.hidden = true;
    var verifyButton = el('button', 'halo-audit__button', 'Confirm'); verifyButton.type = 'button'; verifyButton.hidden = true;
    var note = el('p', 'halo-audit__status');
    [name, email, sendButton, code, verifyButton, note].forEach(function (node) { box.appendChild(node); });

    function say(text, isError) {
      note.textContent = text;
      note.classList.toggle('halo-audit__status--error', !!isError);
    }

    sendButton.addEventListener('click', function () {
      if (!name.value.trim() || !email.value.trim()) { say('Enter your name and email.', true); return; }
      sendButton.disabled = true;
      api('POST', 'gate/send', { email: email.value.trim(), report_id: self.data.report_id }).then(function () {
        code.hidden = false;
        verifyButton.hidden = false;
        sendButton.textContent = 'Send a new code';
        say('We sent a code to ' + email.value.trim() + '. It is valid for 15 minutes.');
        code.focus();
      }).catch(function (error) { say(error.message, true); }).then(function () { sendButton.disabled = false; });
    });

    verifyButton.addEventListener('click', function () {
      verifyButton.disabled = true;
      api('POST', 'gate/verify', {
        email: email.value.trim(), name: name.value.trim(), code: code.value.trim(), report_id: self.data.report_id
      }).then(function (full) {
        self.data.unlocked = true;
        self.data.checks = full.checks;
        clear(box);
        self.renderFindings();
        then();
      }).catch(function (error) { say(error.message, true); }).then(function () { verifyButton.disabled = false; });
    });

    name.focus();
  };

  Widget.prototype.loadFull = function () {
    var self = this;
    api('GET', 'report/' + this.data.report_id).then(function (full) {
      self.data.checks = full.checks;
      self.data.summary = full.summary;
      self.renderSummary();
      self.renderFindings();
    }).catch(function (error) {
      if (error.status === 403) { self.data.unlocked = false; self.renderGate(function () { self.loadFull(); }); return; }
      self.say(error.message, true);
    });
  };

  Widget.prototype.renderFindings = function () {
    var data = this.data;
    var box = this.findings;
    clear(box);
    if (!data.checks) { return; }

    var groups = {};
    data.checks.forEach(function (row) { (groups[row.group] = groups[row.group] || []).push(row); });

    Object.keys(groups).forEach(function (key) {
      var section = el('section', 'halo-audit__section');
      section.appendChild(el('h3', 'halo-audit__section-title', data.labels[key] || key));
      groups[key].forEach(function (row) {
        var item = el('div', 'halo-audit__check halo-audit__check--' + row.status);
        item.appendChild(el('span', 'halo-audit__badge', STATUS[row.status] || row.status));
        var body = el('div', 'halo-audit__check-body');
        body.appendChild(el('strong', null, row.title));
        if (row.evidence) { body.appendChild(el('p', 'halo-audit__evidence', row.evidence)); }
        if (row.message) { body.appendChild(el('p', 'halo-audit__fix', row.message)); }
        body.appendChild(el('p', 'halo-audit__why', row.why));
        item.appendChild(body);
        section.appendChild(item);
      });
      box.appendChild(section);
    });

    var scan = el('section', 'halo-audit__section halo-audit__scan');
    scan.appendChild(el('h3', 'halo-audit__section-title', 'Live AI visibility'));
    scan.appendChild(el('p', null, 'Coming soon. This will show whether ChatGPT, Gemini and Google AI Overviews name your organisation.'));
    box.appendChild(scan);
  };

  Widget.prototype.downloadPdf = function () {
    var self = this;
    send('GET', 'report/' + this.data.report_id + '/pdf', null, false).then(function (response) {
      if (!response.ok) { throw new Error('The PDF could not be made. Try again.'); }
      return response.blob();
    }).then(function (blob) {
      var link = el('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'halo-audit.pdf';
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
    }).catch(function (error) { self.say(error.message, true); });
  };

  function boot() {
    var nodes = document.querySelectorAll('.halo-audit');
    for (var i = 0; i < nodes.length; i++) { new Widget(nodes[i]); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
