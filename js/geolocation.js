function updateStatusElement(statusEl, msg, isError, state) {
    if (!statusEl) return;

    var nextState = state || (isError ? 'error' : 'ready');
    var isIcon = statusEl.classList.contains('location-indicator');

    if (isIcon) {
        statusEl.dataset.status = nextState;
        statusEl.setAttribute('title', msg);
        statusEl.setAttribute('aria-label', msg);
        statusEl.style.display = 'inline-flex';
        return;
    }

    statusEl.textContent = msg;
    statusEl.style.color = isError ? '#c00' : '#008369';
    statusEl.style.display = 'block';
}

function requestLocation(latFieldId, lngFieldId, statusElementId) {
    var statusEl = statusElementId ? document.getElementById(statusElementId) : null;
    function setStatus(msg, isError, state) {
        updateStatusElement(statusEl, msg, isError, state);
    }

    if (!navigator.geolocation) {
        setStatus('Geolocation is not supported by this browser.', true, 'error');
        return;
    }

    setStatus('Requesting location...', false, 'loading');
    navigator.geolocation.getCurrentPosition(
        function (position) {
            var lat = position.coords.latitude.toFixed(6);
            var lng = position.coords.longitude.toFixed(6);
            var latField = document.getElementById(latFieldId);
            var lngField = document.getElementById(lngFieldId);
            if (latField && lngField) {
                latField.value = lat;
                lngField.value = lng;
                setStatus('Location captured.', false, 'ready');
            } else {
                setStatus('Unable to set location fields.', true, 'error');
            }
        },
        function (error) {
            var message = 'Unable to get location.';
            if (error.code === error.PERMISSION_DENIED) {
                message = 'Location permission denied.';
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                message = 'Location unavailable.';
            } else if (error.code === error.TIMEOUT) {
                message = 'Location request timed out.';
            }
            setStatus(message, true, 'error');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
    );
}

// Starts a background heartbeat to fetch location and optionally send it to a backend endpoint.
function startLocationHeartbeat(latFieldId, lngFieldId, statusElementId, endpointUrl, intervalMs) {
    var interval = intervalMs || 10 * 60 * 1000; // default 10 minutes
    var statusEl = statusElementId ? document.getElementById(statusElementId) : null;
    function setStatus(msg, isError, state) {
        updateStatusElement(statusEl, msg, isError, state);
    }
    function sendLocationOnce() {
        if (!navigator.geolocation) {
            setStatus('Geolocation not supported in this browser.', true, 'error');
            return;
        }
        setStatus('Updating location...', false, 'loading');
        navigator.geolocation.getCurrentPosition(
            function (position) {
                var lat = position.coords.latitude.toFixed(6);
                var lng = position.coords.longitude.toFixed(6);
                if (latFieldId && lngFieldId) {
                    var latField = document.getElementById(latFieldId);
                    var lngField = document.getElementById(lngFieldId);
                    if (latField && lngField) {
                        latField.value = lat;
                        lngField.value = lng;
                    }
                }
                if (endpointUrl) {
                    fetch(endpointUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng),
                        credentials: 'same-origin'
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error('Status ' + res.status);
                        }
                        setStatus('Location updated.', false, 'ready');
                    }).catch(function () {
                        setStatus('Could not send location to server.', true, 'error');
                    });
                } else {
                    setStatus('Location captured.', false, 'ready');
                }
            },
            function (error) {
                var message = 'Unable to get location.';
                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Location permission denied.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Location unavailable.';
                } else if (error.code === error.TIMEOUT) {
                    message = 'Location request timed out.';
                }
                setStatus(message, true, 'error');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
        );
    }

    if (statusEl && statusEl.classList.contains('location-indicator') && !statusEl.dataset.refreshBound) {
        statusEl.dataset.refreshBound = 'true';
        statusEl.addEventListener('click', function () {
            setStatus('Updating location...', false, 'loading');
            sendLocationOnce();
        });
    }

    sendLocationOnce();
    return setInterval(sendLocationOnce, interval);
}
