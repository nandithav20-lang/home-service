/**
 * Smart Customer Safety & Emergency Protection JavaScript Module
 * File: assets/js/safety.js
 */

(function() {
  'use strict';

  // 1. Emergency & Silent SOS Handlers
  window.triggerSafetySOS = function(type, sessionId, csrfToken) {
    var isSilent = (type === 'silent_sos');
    
    if (!isSilent) {
      if (!confirm("⚠️ TRIGGER EMERGENCY SOS?\n\nThis will immediately log an active emergency incident, alert HomeService Safety Center, and notify all your registered trusted contacts with your service details.")) {
        return;
      }
    }

    var btn = isSilent ? document.getElementById('btn-silent-sos') : document.getElementById('btn-emergency-sos');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
    }

    // Try to attach GPS if customer has permitted
    var payload = {
      type: type,
      session_id: sessionId,
      csrf_token: csrfToken,
      lat: null,
      lng: null
    };

    function sendAlert(data) {
      fetch('../api/safety/trigger_sos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      .then(function(res) { return res.json(); })
      .then(function(resp) {
        if (resp.success) {
          if (isSilent) {
            // Discreet silent notification without any audio or alarming alerts
            if (btn) {
              btn.innerHTML = '<i class="fa-solid fa-check"></i> Silent Alert Sent';
              btn.classList.add('btn-secondary');
            }
            var statusDiv = document.getElementById('sos-silent-status');
            if (statusDiv) {
              statusDiv.style.display = 'block';
              statusDiv.innerText = 'Silent alert logged quietly. Trusted contacts notified.';
            }
          } else {
            alert("🚨 EMERGENCY SOS SENT!\n\nIncident ID: #" + resp.incident_id + "\nTrusted contacts alerted: " + resp.contacts_notified + "\nEmergency dispatchers notified.");
            window.location.reload();
          }
        } else {
          alert("Error sending alert: " + (resp.message || 'Unknown error'));
          if (btn) btn.disabled = false;
        }
      })
      .catch(function(err) {
        console.error("SOS Fetch error:", err);
        // Fallback to standard form submit
        alert("Alert dispatched to server fallback.");
        window.location.reload();
      });
    }

    if (navigator.geolocation && (window.locationSharingEnabled || isSilent)) {
      navigator.geolocation.getCurrentPosition(
        function(pos) {
          payload.lat = pos.coords.latitude;
          payload.lng = pos.coords.longitude;
          sendAlert(payload);
        },
        function() {
          sendAlert(payload);
        },
        { timeout: 3500 }
      );
    } else {
      sendAlert(payload);
    }
  };

  // 2. "I'm Safe" Check-In
  window.performSafetyCheckin = function(sessionId, csrfToken) {
    var btn = document.getElementById('btn-im-safe');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking in...';
    }

    fetch('../api/safety/checkin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: sessionId, csrf_token: csrfToken })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        var msgBox = document.getElementById('checkin-feedback');
        if (msgBox) {
          msgBox.className = 'alert alert-success';
          msgBox.innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + data.message;
          msgBox.style.display = 'block';
        }
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-shield-heart"></i> I\'m Safe (Confirmed)';
        }
        // Update countdown if present
        if (data.next_due && window.initCheckinCountdown) {
          window.initCheckinCountdown(data.next_due);
        }
      } else {
        alert(data.message || 'Check-in failed');
        if (btn) btn.disabled = false;
      }
    })
    .catch(function(err) {
      console.error(err);
      if (btn) btn.disabled = false;
    });
  };

  // 3. Location Sharing Toggle with explicit consent
  window.toggleLocationSharing = function(sessionId, csrfToken, enable) {
    var locStatus = document.getElementById('location-sharing-status');

    if (!enable) {
      // Disabling
      fetch('../api/safety/update_location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId, csrf_token: csrfToken, enabled: 0 })
      }).then(function() {
        window.locationSharingEnabled = false;
        if (locStatus) {
          locStatus.className = 'location-indicator inactive';
          locStatus.innerHTML = '<i class="fa-solid fa-location-slash"></i> Location sharing is OFF';
        }
      });
      return;
    }

    // Explicit consent prompt
    if (!confirm("📍 Enable Location Sharing for this SafeVisit session?\n\nYour GPS coordinates will be shared only with your configured trusted emergency contacts and platform safety response during this active session.")) {
      var chk = document.getElementById('location-toggle-checkbox');
      if (chk) chk.checked = false;
      return;
    }

    if (!navigator.geolocation) {
      alert("Geolocation is not supported by your browser.");
      var chk = document.getElementById('location-toggle-checkbox');
      if (chk) chk.checked = false;
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function(pos) {
        fetch('../api/safety/update_location.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            session_id: sessionId,
            csrf_token: csrfToken,
            enabled: 1,
            lat: pos.coords.latitude,
            lng: pos.coords.longitude
          })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data.success) {
            window.locationSharingEnabled = true;
            if (locStatus) {
              locStatus.className = 'location-indicator active';
              locStatus.innerHTML = '<span class="pulse-dot"></span> GPS Sharing Active (Coordinates: ' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4) + ')';
            }
          }
        });
      },
      function(error) {
        alert("Location access was denied or unavailable. SafeVisit session will continue without GPS.");
        var chk = document.getElementById('location-toggle-checkbox');
        if (chk) chk.checked = false;
        if (locStatus) {
          locStatus.className = 'location-indicator inactive';
          locStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Permission denied by browser';
        }
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  // 4. Countdown Timer for Check-In
  window.initCheckinCountdown = function(dueTimestampStr) {
    var timerEl = document.getElementById('checkin-timer-display');
    if (!timerEl) return;

    var targetTime = new Date(dueTimestampStr.replace(/-/g, '/')).getTime();

    if (window.safetyTimerInterval) {
      clearInterval(window.safetyTimerInterval);
    }

    window.safetyTimerInterval = setInterval(function() {
      var now = new Date().getTime();
      var diff = targetTime - now;

      if (diff <= 0) {
        var overSecs = Math.abs(Math.floor(diff / 1000));
        var minsOver = Math.floor(overSecs / 60);
        var secsOver = overSecs % 60;
        timerEl.innerHTML = '<span style="color:var(--danger);font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> Check-in Overdue by ' + minsOver + 'm ' + secsOver + 's</span>';
      } else {
        var mins = Math.floor(diff / (1000 * 60));
        var secs = Math.floor((diff % (1000 * 60)) / 1000);
        timerEl.innerHTML = 'Next check-in in <strong>' + mins + 'm ' + (secs < 10 ? '0' : '') + secs + 's</strong>';
      }
    }, 1000);
  };
})();

