// Front-end validation and progressive enhancement for the contact form.
// The server validates everything again; this only improves the user experience.
// DOM updates use textContent only.
(function () {
  'use strict';

  var form = document.getElementById('contact-form');
  if (!form) return;

  var LIMITS = { name: 100, subject: 150, messageMin: 10, messageMax: 5000 };
  var EMAIL_RE = /^[^\s@<>()[\]\\,;:"]+@[^\s@<>()[\]\\,;:"]+\.[^\s@<>()[\]\\,;:"]{2,}$/;
  var CONTROL_RE = /[\u0000-\u001f\u007f]/;
  var status = document.getElementById('form-status');
  var counter = document.getElementById('message-counter');

  function setError(field, text) {
    var input = form.elements[field];
    var box = document.getElementById(field + '-error');
    if (box) box.textContent = text || '';
    if (input) input.setAttribute('aria-invalid', text ? 'true' : 'false');
  }

  function validate() {
    var v = function (name) { return String(form.elements[name].value || '').trim(); };
    var errors = {};
    var name = v('name');
    var email = v('email');
    var subject = v('subject');
    var message = v('message');
    if (!name || name.length > LIMITS.name || CONTROL_RE.test(name)) errors.name = 'Please enter your name.';
    if (!EMAIL_RE.test(email) || email.length > 254) errors.email = 'Please enter a valid e-mail address.';
    if (!subject || subject.length > LIMITS.subject || CONTROL_RE.test(subject)) errors.subject = 'Please enter a subject.';
    if (message.length < LIMITS.messageMin || message.length > LIMITS.messageMax) {
      errors.message = 'The message must be between ' + LIMITS.messageMin + ' and ' + LIMITS.messageMax + ' characters.';
    }
    ['name', 'email', 'subject', 'message'].forEach(function (f) { setError(f, errors[f]); });
    return Object.keys(errors).length === 0;
  }

  form.elements.message.addEventListener('input', function () {
    counter.textContent = form.elements.message.value.length + ' / ' + LIMITS.messageMax;
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    status.textContent = '';
    status.className = 'status';
    if (!validate()) {
      status.textContent = 'Please correct the highlighted fields.';
      status.className = 'status error';
      return;
    }
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        status.textContent = String(data.message || '');
        status.className = data.ok ? 'status ok' : 'status error';
        Object.keys(data.errors || {}).forEach(function (f) { setError(f, String(data.errors[f])); });
        if (data.ok) {
          form.reset();
          counter.textContent = '0 / ' + LIMITS.messageMax;
        }
      })
      .catch(function () {
        status.textContent = 'Network error - please try again.';
        status.className = 'status error';
      })
      .finally(function () { button.disabled = false; });
  });
})();
