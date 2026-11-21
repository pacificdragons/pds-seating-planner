/**
 * Test-only driver for the seating planner admin UI. Exposes window.SPH with
 * synthetic touch/mouse gestures and a DOM+model snapshot, so Playwright specs
 * can reproduce real finger interactions (jQuery UI + touch-punch) deterministically.
 *
 * NOT shipped to production (see .gitattributes export-ignore). Lives only to
 * drive fixture.html under test.
 */
(function () {
  function $() { return window.jQuery; }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function rect(sel) {
    var el = document.querySelector(sel);
    return el ? el.getBoundingClientRect() : null;
  }
  function center(r) { return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; }

  // The × of a filled seat (its top-right corner target).
  function xPoint(pos) {
    var r = rect('.position[data-position="' + pos + '"] .remove-paddler');
    return r ? center(r) : null;
  }
  // A point on the chip body, well clear of the × (used for a "grab by name" drag).
  function bodyPoint(pos) {
    var r = rect('.position[data-position="' + pos + '"] .assigned-paddler');
    return r ? { x: r.left + r.width * 0.15, y: r.top + r.height / 2 } : null;
  }
  function seatCenter(pos) {
    var r = rect('.position[data-position="' + pos + '"]');
    return r ? center(r) : null;
  }
  function availCenter(userId) {
    var r = rect('#available-paddlers .paddler-item[data-user-id="' + userId + '"]');
    return r ? center(r) : null;
  }
  function availZoneCenter() {
    var r = rect('#available-paddlers');
    return r ? center(r) : null;
  }

  function mkTouch(target, x, y) {
    return new Touch({
      identifier: 1, target: target,
      clientX: x, clientY: y, pageX: x, pageY: y, screenX: x, screenY: y,
      radiusX: 10, radiusY: 10, force: 1
    });
  }
  function fireTouch(type, target, x, y) {
    var t = mkTouch(target, x, y);
    var end = type === 'touchend';
    target.dispatchEvent(new TouchEvent(type, {
      cancelable: true, bubbles: true, composed: true,
      touches: end ? [] : [t], targetTouches: end ? [] : [t], changedTouches: [t]
    }));
  }

  // A touch drag that respects jQuery UI draggable's delay:100 / distance:5.
  // Touch events stay targeted at the start element for the whole gesture
  // (real device behaviour), which is what makes the ×-grab swap bug reproduce.
  async function touchDrag(sx, sy, tx, ty) {
    var start = document.elementFromPoint(sx, sy);
    fireTouch('touchstart', start, sx, sy);
    await sleep(160);
    for (var i = 1; i <= 8; i++) {
      fireTouch('touchmove', start, sx + (tx - sx) * i / 8, sy + (ty - sy) * i / 8);
      await sleep(20);
    }
    fireTouch('touchend', start, tx, ty);
    await sleep(100);
  }

  // A touch tap (no movement) at a point.
  async function touchTap(x, y) {
    var el = document.elementFromPoint(x, y);
    fireTouch('touchstart', el, x, y);
    await sleep(30);
    fireTouch('touchend', el, x, y);
    await sleep(160);
  }

  async function mouseClick(x, y) {
    var el = document.elementFromPoint(x, y);
    ['mousedown', 'mouseup', 'click'].forEach(function (ty) {
      el.dispatchEvent(new MouseEvent(ty, { bubbles: true, cancelable: true, clientX: x, clientY: y }));
    });
    await sleep(160);
  }

  window.SPH = {
    // Drag the paddler in `fromPos` onto `toPos`. grab: 'x' (on the × corner)
    // or 'body' (on the name). Mirrors how a finger grabs a small scaled chip.
    async swap(fromPos, toPos, grab) {
      var start = grab === 'x' ? xPoint(fromPos) : bodyPoint(fromPos);
      var end = seatCenter(toPos);
      await touchDrag(start.x, start.y, end.x, end.y);
    },
    // Tap the × of a filled seat (touch remove).
    async tapRemove(pos) {
      var p = xPoint(pos);
      await touchTap(p.x, p.y);
    },
    // Desktop mouse-click the × of a filled seat.
    async clickRemove(pos) {
      var p = xPoint(pos);
      await mouseClick(p.x, p.y);
    },
    // Drag an available paddler onto a seat (assign / swap-from-available).
    async assign(userId, pos) {
      var s = availCenter(userId);
      var e = seatCenter(pos);
      await touchDrag(s.x, s.y, e.x, e.y);
    },
    // Drag a seated paddler (by body) back to the available list.
    async unseat(pos) {
      var s = bodyPoint(pos);
      var e = availZoneCenter();
      await touchDrag(s.x, s.y, e.x, e.y);
    },
    // DOM + saved-model snapshot. `avail` and seat ids are stringified so
    // assertions don't trip over jQuery .data() number/string coercion.
    snap() {
      var j = $();
      var seats = {};
      j('.position.position-filled').each(function () {
        seats[j(this).data('position')] = String(j(this).find('.assigned-paddler').data('user-id'));
      });
      var avail = j('#available-paddlers .paddler-item').map(function () {
        return String(j(this).data('user-id'));
      }).get();
      var model = {};
      try {
        var boats = JSON.parse(j('#seating-plan-data').val()).boats || [];
        (boats[0] ? Object.keys(boats[0]) : []).forEach(function (pos) {
          model[pos] = String(boats[0][pos].userId);
        });
      } catch (e) { model = { parseError: String(e) }; }
      return { avail: avail, seats: seats, model: model };
    }
  };
})();
