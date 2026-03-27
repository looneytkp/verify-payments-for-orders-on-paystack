document.addEventListener("click", function (e) {
  const btn = e.target.closest(".baby_verify_payment");
  if (!btn || typeof babyVpFrontend === "undefined") return;

  const order = btn.dataset.order || "";
  const key = btn.dataset.key || "";
  const nonce = btn.dataset.nonce || "";
  const badge = btn.parentNode ? btn.parentNode.querySelector(".baby-vp-badge") : null;
  const messages = babyVpFrontend.messages || {};

  btn.classList.add("is-loading");

  if (badge) {
    badge.className = "baby-vp-badge is-info";
    badge.innerHTML = '<span class="dot"></span> ' + (messages.verifying || "Verifying...");
  }

  const form = new FormData();
  form.append("action", "baby_verify_payment");
  form.append("order_id", order);
  form.append("order_key", key);
  form.append("nonce", nonce);

  fetch(babyVpFrontend.ajaxUrl, {
    method: "POST",
    body: form,
    credentials: "same-origin"
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      btn.classList.remove("is-loading");

      if (data && data.success) {
        if (badge) {
          badge.className = "baby-vp-badge is-success";
          badge.innerHTML = '<span class="dot"></span> ' + (messages.verified || "Verified ✓");
        }

        btn.disabled = true;

        const extra1 = document.createElement("span");
        extra1.className = "baby-vp-badge is-success";
        extra1.innerHTML = '<span class="dot"></span> ' + (messages.statusChanged || "Order changed to Processing ✓");
        btn.parentNode.appendChild(extra1);

        const extra2 = document.createElement("span");
        extra2.className = "baby-vp-badge is-info";
        extra2.innerHTML = '<span class="dot"></span> ' + (messages.notificationSent || "Order notification sent ✓");
        btn.parentNode.appendChild(extra2);
      } else {
        const msg = (data && data.data && data.data.message)
          ? data.data.message
          : (messages.verificationFailed || "Verification failed.");

        if (badge) {
          badge.className = "baby-vp-badge is-error";
          badge.innerHTML = '<span class="dot"></span> ' + msg;
        }
      }
    })
    .catch(function () {
      btn.classList.remove("is-loading");

      if (badge) {
        badge.className = "baby-vp-badge is-error";
        badge.innerHTML = '<span class="dot"></span> ' + (messages.networkError || "Network error");
      }
    });
});

document.addEventListener("submit", function (e) {
  if (typeof babyVpFrontend === "undefined" || !babyVpFrontend.isTrackPage) return;

  const form = e.target.closest("form.woocommerce-form-track-order");
  if (!form) return;

  if (form.dataset.babyPsAutoSubmit === "1") {
    delete form.dataset.babyPsAutoSubmit;
    return;
  }

  const wcOrderInput = form.querySelector("#orderid");
  const wcEmailInput = form.querySelector("#order_email");

  const refInput = form.querySelector("#baby_ps_reference");
  const psEmailInput = form.querySelector("#baby_ps_email");

  const errBox = document.querySelector(".baby-ps-track-error");
  const messages = babyVpFrontend.messages || {};

  const wcOrder = wcOrderInput ? wcOrderInput.value.trim() : "";
  const wcEmail = wcEmailInput ? wcEmailInput.value.trim() : "";

  const ref = refInput ? refInput.value.trim() : "";
  const psEmail = psEmailInput ? psEmailInput.value.trim() : "";

  if (errBox) {
    errBox.textContent = "";
  }

  // Do not allow mixing both methods at once
  if (wcOrder && ref) {
    e.preventDefault();
    if (errBox) {
      errBox.textContent = messages.chooseOneTrackingMethod || "Please use either Order ID or Paystack reference, not both.";
    }
    return;
  }

  // Path 1: Order ID + Email
  if (wcOrder) {
    if (!wcEmail) {
      e.preventDefault();
      if (errBox) {
        errBox.textContent = messages.missingBillingEmail || "Please enter your billing email.";
      }
      return;
    }

    // Normal WooCommerce submit
    return;
  }

  // Path 2: Paystack Reference + Email
  if (ref) {
    e.preventDefault();

    if (!psEmail) {
      if (errBox) {
        errBox.textContent = messages.missingBillingEmail || "Please enter your billing email.";
      }
      return;
    }

    if (errBox) {
      errBox.textContent = messages.checkingDetails || "Checking details...";
    }

    const fd = new FormData();
    fd.append("action", "baby_track_by_paystack");
    fd.append("reference", ref);
    fd.append("email", psEmail);
    fd.append("nonce", babyVpFrontend.trackNonce || "");

    fetch(babyVpFrontend.ajaxUrl, {
      method: "POST",
      body: fd,
      credentials: "same-origin"
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success && data.data && data.data.orderid && data.data.email) {
          if (!wcOrderInput || !wcEmailInput) {
            if (errBox) {
              errBox.textContent = messages.trackingInputsNotFound || "Tracking inputs not found.";
            }
            return;
          }

          wcOrderInput.value = data.data.orderid;
          wcEmailInput.value = data.data.email;
          form.dataset.babyPsAutoSubmit = "1";
          form.submit();
          return;
        }

        if (errBox) {
          errBox.textContent = (data && data.data && data.data.message)
            ? data.data.message
            : (messages.noMatchingOrder || "No matching order found.");
        }
      })
      .catch(function () {
        if (errBox) {
          errBox.textContent = messages.trackNetworkError || "Network error. Please try again.";
        }
      });

    return;
  }

  // If email is entered alone without Order ID or reference
  if (wcEmail || psEmail) {
    e.preventDefault();
    if (errBox) {
      errBox.textContent = messages.missingOrderOrReference || "Please enter your Order ID or Paystack reference.";
    }
    return;
  }

  // Nothing entered
  e.preventDefault();
  if (errBox) {
    errBox.textContent = messages.missingOrderOrReference || "Please enter your Order ID or Paystack reference.";
  }
});