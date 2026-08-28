(function ($) {
  "use strict";

  /**
   * Dragon Boat Seating Planner Admin JavaScript
   *
   * Handles all drag-and-drop functionality, boat management, and seating arrangement
   * for the dragon boat seating planner metabox.
   */

  $(document).ready(function () {
    // Only initialize if we're on a page with the seating planner
    if ($("#pds-seating-planner-app").length === 0) return;

    var seatingData = {
      boats: [],
      metadata: {
        boat2Visible: null, // null = auto (based on paddler count), true/false = user preference
      },
    };
    var totalPaddlers = $(".paddler-item").length;
    var defaultBoatCount = totalPaddlers > 20 ? 2 : 1;
    var actualBoatCount = defaultBoatCount;
    var boatCount = actualBoatCount;

    // Load existing seating data first
    var existingData = $("#seating-plan-data").val();
    if (existingData) {
      try {
        seatingData = JSON.parse(existingData);
      } catch (e) {
        console.log("Error parsing existing seating data");
      }
    }

    // Backwards compatibility: ensure metadata exists and has isDraft
    if (!seatingData.metadata) {
      seatingData.metadata = {};
    }
    if (typeof seatingData.metadata.isDraft === "undefined") {
      seatingData.metadata.isDraft = false;
    }

    // Determine actual boat count from the generic boatCount model, falling
    // back to the legacy boat2Visible flag and finally the paddler-count default.
    actualBoatCount = resolveInitialBoatCount();

    // Persist the resolved count so every save carries the generic model forward.
    seatingData.metadata.boatCount = actualBoatCount;
    // Keep the legacy flag in sync for any older reader still checking it.
    seatingData.metadata.boat2Visible = actualBoatCount >= 2;

    // Initialize boats
    initializeBoats(actualBoatCount);

    // Setup global controls based on boat count
    setupGlobalControls(actualBoatCount);

    // Ensure we have the right number of boat objects
    while (seatingData.boats.length < actualBoatCount) {
      seatingData.boats.push({});
    }
    
    // Convert arrays to objects if needed (for backward compatibility)
    for (var i = 0; i < seatingData.boats.length; i++) {
      if (Array.isArray(seatingData.boats[i])) {
        seatingData.boats[i] = {};
      }
    }

    // Load seating arrangement
    if (existingData) {
      loadSeatingArrangement();
    }

    // Update global reference for other functions
    boatCount = actualBoatCount;

    // Initialise draft button UI to match loaded state
    updateDraftButton();

    // Wire up the scale control (shrinks the whole boat layout so more boats
    // fit per row on narrow desktops). This is a per-user view preference, not
    // part of the saved seating plan, so it lives in localStorage.
    setupScaleControl();

    // Make paddler items draggable with touch support
    $(".paddler-item").draggable({
      revert: "invalid",
      helper: "clone",
      zIndex: 1000,
      scroll: false,
      distance: 5, // Minimum distance to start drag (helpful for touch)
      delay: 100, // Short delay to prevent accidental drags
    });

    function initializeBoats(count) {
      var boatsContainer = $("#boats-container");
      boatsContainer.empty();

      for (var i = 0; i < count; i++) {
        var boatElement = createBoatFromTemplate(i, count);
        boatsContainer.append(boatElement);
      }

      // Re-initialize drag/drop after creating boats
      initializeDragDrop();
    }

    function createBoatFromTemplate(boatIndex, totalBoats) {
      var template = document.getElementById("boat-template");
      var clone = document.importNode(template.content, true);

      var boatContainer = clone.querySelector(".boat-container");
      boatContainer.setAttribute("data-boat", boatIndex);

      var boatTitleElement = clone.querySelector(".boat-title");

      if (totalBoats > 1) {
        // Multiple boats - show title and empty button
        var boatTitle = "Boat " + (boatIndex + 1);
        boatTitleElement.textContent = boatTitle;
      } else {
        // Single boat - remove the header entirely
        var boatHeader = clone.querySelector(".boat-header");
        if (boatHeader) {
          boatHeader.remove();
        }
      }

      // Set boat index for all elements with data-boat attribute
      var elementsWithBoatData = clone.querySelectorAll('[data-boat=""]');
      elementsWithBoatData.forEach(function (element) {
        element.setAttribute("data-boat", boatIndex);
      });

      return clone;
    }

    // Index of the last boat that actually holds a paddler, or -1 if none do.
    function highestPopulatedBoatIndex() {
      var idx = -1;
      for (var i = 0; i < seatingData.boats.length; i++) {
        var boat = seatingData.boats[i];
        if (boat && typeof boat === "object" && Object.keys(boat).length > 0) {
          idx = i;
        }
      }
      return idx;
    }

    // Work out how many boats to show on first render. Prefer the generic
    // boatCount model; fall back to the legacy boat2Visible flag; otherwise use
    // the paddler-count default. Never show fewer boats than actually hold data.
    function resolveInitialBoatCount() {
      var meta = seatingData.metadata || {};
      var base;
      if (typeof meta.boatCount === "number" && meta.boatCount >= 1) {
        base = meta.boatCount;
      } else if (meta.boat2Visible === true) {
        base = 2;
      } else if (meta.boat2Visible === false) {
        base = 1;
      } else {
        base = defaultBoatCount;
      }
      return Math.max(base, highestPopulatedBoatIndex() + 1, 1);
    }

    function setupGlobalControls(count) {
      var globalControls = $("#global-controls");
      globalControls.empty();

      // A single boat has no per-boat header, so it needs a global Empty button.
      if (count === 1) {
        globalControls.append(
          '<button type="button" id="empty-boat" class="button button-secondary" style="margin: 5px;">' +
            '<span class="dashicons dashicons-dismiss" style="margin-right: 5px;"></span>Empty Boat' +
            "</button>"
        );
      }

      // Always allow adding another boat.
      globalControls.append(
        '<button type="button" id="add-boat" class="button button-secondary" style="margin: 5px;">' +
          '<span class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></span>Add boat' +
          "</button>"
      );

      // Allow removing the last boat once there is more than one.
      if (count > 1) {
        globalControls.append(
          '<button type="button" id="remove-last-boat" class="button button-secondary" style="margin: 5px;">' +
            '<span class="dashicons dashicons-minus" style="margin-right: 5px;"></span>Remove Boat ' +
            count +
            "</button>"
        );
      }
    }

    function initializeDragDrop() {
      // Make available paddlers area droppable for returning paddlers
      $("#available-paddlers").droppable({
        accept: ".assigned-paddler",
        hoverClass: "drop-zone-hover",
        drop: function (event, ui) {
          var userId = ui.draggable.data("user-id");
          var userName = ui.draggable.hasClass("assigned-paddler")
            ? ui.draggable.find(".paddler-name").text().trim()
            : ui.draggable.text().trim();

          // Find and clear the position this paddler was in
          var positionElement = ui.draggable.closest(".position");
          var position = positionElement.data("position");
          var boatIndex = positionElement.data("boat");

          positionElement.html(
            '<span class="position-label">' +
              getPositionLabel(position) +
              "</span>"
          );
          positionElement.removeClass("position-filled");
          if (seatingData.boats[boatIndex]) {
            delete seatingData.boats[boatIndex][position];
          }

          // Add paddler back to available list
          var paddlerItem = $(
            '<div class="paddler-item" draggable="true" data-user-id="' +
              userId +
              '">' +
              userName +
              "</div>"
          );
          $(this).append(paddlerItem);

          // Make the new item draggable
          paddlerItem.draggable({
            revert: "invalid",
            helper: "clone",
            zIndex: 1000,
            scroll: false,
            distance: 5,
            delay: 100,
          });

          updateSeatingDataInput();
        },
      });

      // Make positions droppable
      $(".position").droppable({
        accept: ".paddler-item, .assigned-paddler",
        hoverClass: "position-hover",
        drop: function (event, ui) {
          var position = $(this).data("position");
          var boatIndex = $(this).data("boat");
          var userId = ui.draggable.data("user-id");
          var userName = ui.draggable.hasClass("assigned-paddler")
            ? ui.draggable.find(".paddler-name").text().trim()
            : ui.draggable.text().trim();
          var isAssignedPaddler = ui.draggable.hasClass("assigned-paddler");

          // Ensure boat object exists
          if (!seatingData.boats[boatIndex]) {
            seatingData.boats[boatIndex] = {};
          }

          // Handle position exchange if this position is already occupied
          if ($(this).hasClass("position-filled")) {
            var currentAssignedPaddler = $(this).find(".assigned-paddler");
            var currentUserId = currentAssignedPaddler.data("user-id");
            var currentUserName = currentAssignedPaddler
              .find(".paddler-name")
              .text()
              .trim();

            if (isAssignedPaddler) {
              // Exchange positions between two assigned paddlers
              var sourcePositionElement = ui.draggable.closest(".position");
              var sourcePosition = sourcePositionElement.data("position");
              var sourceBoatIndex = sourcePositionElement.data("boat");

              // Move current paddler to source position
              sourcePositionElement.html(
                '<span class="assigned-paddler draggable" data-user-id="' +
                  currentUserId +
                  '"><span class="paddler-name">' +
                  currentUserName +
                  '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>'
              );
              sourcePositionElement.addClass("position-filled");

              // Make the moved paddler draggable
              var movedPaddlerElement = sourcePositionElement.find(".assigned-paddler");
              movedPaddlerElement.draggable({
                revert: "invalid",
                helper: "clone",
                zIndex: 1000,
                scroll: false,
                distance: 5,
                delay: 100,
              });

              // Ensure source boat object exists
              if (!seatingData.boats[sourceBoatIndex]) {
                seatingData.boats[sourceBoatIndex] = {};
              }

              // Update seating data for source position
              seatingData.boats[sourceBoatIndex][sourcePosition] = {
                userId: currentUserId,
                userName: currentUserName,
              };
            } else {
              // Move current paddler back to available list
              var paddlerItem = $(
                '<div class="paddler-item" draggable="true" data-user-id="' +
                  currentUserId +
                  '">' +
                  currentUserName +
                  "</div>"
              );
              $("#available-paddlers").append(paddlerItem);

              // Make the new item draggable
              paddlerItem.draggable({
                revert: "invalid",
                helper: "clone",
                zIndex: 1000,
              });
            }
          } else if (isAssignedPaddler) {
            // Clear the source position
            var sourcePositionElement = ui.draggable.closest(".position");
            var sourcePosition = sourcePositionElement.data("position");
            var sourceBoatIndex = sourcePositionElement.data("boat");

            sourcePositionElement.html(
              '<span class="position-label">' +
                getPositionLabel(sourcePosition) +
                "</span>"
            );
            sourcePositionElement.removeClass("position-filled");

            if (seatingData.boats[sourceBoatIndex]) {
              delete seatingData.boats[sourceBoatIndex][sourcePosition];
            }
          } else {
            // Clear any existing assignment for this user from other positions
            clearUserFromAllPositions(userId);

            // Remove paddler from available list
            ui.draggable.remove();
          }

          // Add user to this position
          $(this).html(
            '<span class="assigned-paddler draggable" data-user-id="' +
              userId +
              '"><span class="paddler-name">' +
              userName +
              '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>'
          );
          $(this).addClass("position-filled");

          // Make assigned paddler draggable
          var assignedPaddlerElement = $(this).find(".assigned-paddler");
          assignedPaddlerElement.draggable({
            revert: "invalid",
            helper: "clone",
            zIndex: 1000,
            scroll: false,
            distance: 5,
            delay: 100,
          });

          // Update seating data
          seatingData.boats[boatIndex][position] = {
            userId: userId,
            userName: userName,
          };

          updateSeatingDataInput();
        },
      });
    }

    // Handle click on remove button (×) - support both click and touch events for mobile
    $(document).on("click touchend", ".remove-paddler", function (e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Prevent duplicate events from both click and touchend
      if ($(this).data('removing')) {
        return;
      }
      $(this).data('removing', true);
      
      var positionElement = $(this).closest(".position");
      var position = positionElement.data("position");
      var boatIndex = positionElement.data("boat");
      var assignedPaddler = $(this).closest(".assigned-paddler");
      var userId = assignedPaddler.data("user-id");
      var userName = assignedPaddler.find(".paddler-name").text().trim();

      // Add paddler back to available list
      var paddlerItem = $(
        '<div class="paddler-item" draggable="true" data-user-id="' +
          userId +
          '">' +
          userName +
          "</div>"
      );
      $("#available-paddlers").append(paddlerItem);

      // Make the new item draggable
      paddlerItem.draggable({
        revert: "invalid",
        helper: "clone",
        zIndex: 1000,
      });

      // Clear the position
      positionElement.html(
        '<span class="position-label">' + getPositionLabel(position) + "</span>"
      );
      positionElement.removeClass("position-filled");

      if (seatingData.boats[boatIndex]) {
        delete seatingData.boats[boatIndex][position];
      }
      updateSeatingDataInput();
      
      // Clear the removing flag after a short delay to ensure proper event handling
      setTimeout(function() {
        $('.remove-paddler').removeData('removing');
      }, 100);
    });

    function clearUserFromAllPositions(userId) {
      $(".position").each(function () {
        var assignedPaddler = $(this).find(
          '.assigned-paddler[data-user-id="' + userId + '"]'
        );
        if (assignedPaddler.length) {
          var position = $(this).data("position");
          var boatIndex = $(this).data("boat");
          var userName = assignedPaddler.find(".paddler-name").text().trim();

          // Add paddler back to available list
          var paddlerItem = $(
            '<div class="paddler-item" draggable="true" data-user-id="' +
              userId +
              '">' +
              userName +
              "</div>"
          );
          $("#available-paddlers").append(paddlerItem);

          // Make the new item draggable
          paddlerItem.draggable({
            revert: "invalid",
            helper: "clone",
            zIndex: 1000,
            scroll: false,
            distance: 5,
            delay: 100,
          });

          $(this).html(
            '<span class="position-label">' +
              getPositionLabel(position) +
              "</span>"
          );
          $(this).removeClass("position-filled");

          if (seatingData.boats[boatIndex]) {
            delete seatingData.boats[boatIndex][position];
          }
        }
      });
    }

    function getPositionLabel(position) {
      if (position === "drummer") return "Drummer";
      if (position === "steersperson") return "Steerer";
      // Handle position formats like "left-1", "right-2", etc.
      // Match the template's display format: L1, L2, R1, R2, etc.
      if (position.startsWith("left-")) {
        return "L" + position.substring(5);
      }
      if (position.startsWith("right-")) {
        return "R" + position.substring(6);
      }
      return position.replace(/-/, "").toUpperCase();
    }

    // Save button click handler
    $("#save-seating-plan").on("click", function () {
      var button = $(this);
      var statusDiv = $("#save-status");

      // Show loading state
      button.prop("disabled", true);
      button
        .find(".dashicons")
        .removeClass("dashicons-saved")
        .addClass("dashicons-update");
      button.find("span:not(.dashicons)").text("Saving...");
      statusDiv.html("").removeClass("success error");

      // Get current post ID
      var postId = $("#post_ID").val() || $('input[name="post_ID"]').val();

      // Prepare data for AJAX
      var seatingDataString = JSON.stringify(seatingData);
      var ajaxData = {
        action: "pds_save_seating_plan",
        post_id: postId,
        seating_data: seatingDataString,
        nonce: $("#pds_seating_planner_nonce_field").val(),
      };

      // Debug: Log the data being sent
      console.log("Saving seating data:", seatingDataString);

      // Send AJAX request
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: ajaxData,
        success: function (response) {
          console.log("Server response:", response);
          if (response.success) {
            statusDiv
              .html(
                '<span style="color: #46b450;">✓ Seating plan saved successfully!</span>'
              )
              .addClass("success");
          } else {
            statusDiv
              .html(
                '<span style="color: #dc3232;">✗ Error: ' +
                  (response.data || "Unknown error occurred") +
                  "</span>"
              )
              .addClass("error");
          }
        },
        error: function () {
          statusDiv
            .html(
              '<span style="color: #dc3232;">✗ Network error occurred. Please try again.</span>'
            )
            .addClass("error");
        },
        complete: function () {
          // Reset button state
          button.prop("disabled", false);
          button
            .find(".dashicons")
            .removeClass("dashicons-update")
            .addClass("dashicons-saved");
          button.find("span:not(.dashicons)").text("Save Seating Plan");

          // Clear status after 3 seconds
          setTimeout(function () {
            statusDiv.fadeOut(300, function () {
              $(this).html("").show();
            });
          }, 3000);
        },
      });
    });

    // Draft toggle button click handler
    $("#toggle-draft-mode").on("click", function () {
      seatingData.metadata.isDraft = !seatingData.metadata.isDraft;
      updateDraftButton();
      updateSeatingDataInput();
    });

    function updateDraftButton() {
      var isDraft = seatingData.metadata.isDraft;
      var btn = $("#toggle-draft-mode");
      var icon = btn.find(".dashicons");
      if (isDraft) {
        $("#draft-button-label").text("Draft active – click to publish");
        icon.removeClass("dashicons-hidden").addClass("dashicons-visibility");
        btn.addClass("button-draft-active");
        $("#draft-status").text("Draft mode: this seating plan will not be shown on the frontend until published.");
      } else {
        $("#draft-button-label").text("Set as Draft");
        icon.removeClass("dashicons-visibility").addClass("dashicons-hidden");
        btn.removeClass("button-draft-active");
        $("#draft-status").text("");
      }
    }

    // Global empty boat button click handler (single boat only)
    $(document).on("click", "#empty-boat", function () {
      if (
        confirm(
          "Are you sure you want to empty the boat? This will move all assigned paddlers back to the available list."
        )
      ) {
        emptyBoat(0); // Empty the single boat
      }
    });

    // Per-boat empty button click handler
    $(document).on("click", ".empty-single-boat", function () {
      var boatIndex = $(this).data("boat");
      var boatName = "Boat " + (boatIndex + 1);
      if (
        confirm(
          "Are you sure you want to empty " +
            boatName +
            "? This will move all assigned paddlers back to the available list."
        )
      ) {
        emptyBoat(boatIndex);
      }
    });

    // Add-boat button click handler
    $(document).on("click", "#add-boat", function () {
      addBoat();
    });

    // Remove-last-boat button click handler
    $(document).on("click", "#remove-last-boat", function () {
      removeLastBoat();
    });

    function emptyBoat(boatIndex) {
      // Find all assigned paddlers in the specified boat and move them back to available list
      $('.position-filled[data-boat="' + boatIndex + '"]').each(function () {
        var position = $(this).data("position");
        var assignedPaddler = $(this).find(".assigned-paddler");
        var userId = assignedPaddler.data("user-id");
        var userName = assignedPaddler.find(".paddler-name").text().trim();

        // Add paddler back to available list
        var paddlerItem = $(
          '<div class="paddler-item" draggable="true" data-user-id="' +
            userId +
            '">' +
            userName +
            "</div>"
        );
        $("#available-paddlers").append(paddlerItem);

        // Make the new item draggable
        paddlerItem.draggable({
          revert: "invalid",
          helper: "clone",
          zIndex: 1000,
          scroll: false,
          distance: 5,
          delay: 100,
        });

        // Clear the position
        $(this).html(
          '<span class="position-label">' +
            getPositionLabel(position) +
            "</span>"
        );
        $(this).removeClass("position-filled");
      });

      // Clear seating data for this boat
      if (seatingData.boats[boatIndex]) {
        seatingData.boats[boatIndex] = {};
      }

      // Update the hidden input
      updateSeatingDataInput();

      // Show feedback
      var boatName = boatCount > 1 ? "Boat " + (boatIndex + 1) : "Boat";
      $("#save-status")
        .html(
          '<span style="color: #46b450;">✓ ' +
            boatName +
            " emptied successfully!</span>"
        )
        .addClass("success");
      setTimeout(function () {
        $("#save-status").fadeOut(300, function () {
          $(this).html("").show();
        });
      }, 2000);
    }

    // Rebuild every boat from the current model so titles, headers and controls
    // all match the current boatCount, then restore the saved arrangement.
    function rebuildBoats() {
      $("#boats-container").empty();
      for (var i = 0; i < boatCount; i++) {
        $("#boats-container").append(createBoatFromTemplate(i, boatCount));
      }
      initializeDragDrop();
      loadSeatingArrangement();
      setupGlobalControls(boatCount);
    }

    function addBoat() {
      boatCount = boatCount + 1;
      window.boatCount = boatCount;

      // Record the new count in both the generic and legacy metadata slots.
      seatingData.metadata.boatCount = boatCount;
      seatingData.metadata.boat2Visible = boatCount >= 2;

      // Ensure a data object exists for the new boat.
      while (seatingData.boats.length < boatCount) {
        seatingData.boats.push({});
      }

      rebuildBoats();
      updateSeatingDataInput();

      // Show feedback
      $("#save-status")
        .html(
          '<span style="color: #46b450;">✓ Boat ' +
            boatCount +
            " added successfully!</span>"
        )
        .addClass("success");
      setTimeout(function () {
        $("#save-status").fadeOut(300, function () {
          $(this).html("").show();
        });
      }, 2000);
    }

    function removeLastBoat() {
      if (boatCount <= 1) {
        return;
      }

      var lastIndex = boatCount - 1;

      if (
        !confirm(
          "Are you sure you want to remove Boat " +
            (lastIndex + 1) +
            "? This will move all assigned paddlers from that boat back to the available list."
        )
      ) {
        return;
      }

      // Move all paddlers from the last boat back to the available list.
      $('.position-filled[data-boat="' + lastIndex + '"]').each(function () {
        var assignedPaddler = $(this).find(".assigned-paddler");
        var userId = assignedPaddler.data("user-id");
        var userName = assignedPaddler.find(".paddler-name").text().trim();

        // Add paddler back to available list
        var paddlerItem = $(
          '<div class="paddler-item" draggable="true" data-user-id="' +
            userId +
            '">' +
            userName +
            "</div>"
        );
        $("#available-paddlers").append(paddlerItem);

        // Make the new item draggable
        paddlerItem.draggable({
          revert: "invalid",
          helper: "clone",
          zIndex: 1000,
          scroll: false,
          distance: 5,
          delay: 100,
        });
      });

      // Drop the boat count and its data.
      boatCount = boatCount - 1;
      window.boatCount = boatCount;
      seatingData.metadata.boatCount = boatCount;
      seatingData.metadata.boat2Visible = boatCount >= 2;

      if (seatingData.boats.length > boatCount) {
        seatingData.boats.splice(boatCount);
      }

      // Rebuild so the remaining boats get the correct titles/headers (e.g.
      // dropping back to a single boat removes its per-boat header).
      rebuildBoats();
      updateSeatingDataInput();

      // Show feedback
      $("#save-status")
        .html(
          '<span style="color: #46b450;">✓ Boat ' +
            (lastIndex + 1) +
            " removed successfully!</span>"
        )
        .addClass("success");
      setTimeout(function () {
        $("#save-status").fadeOut(300, function () {
          $(this).html("").show();
        });
      }, 2000);
    }

    function updateSeatingDataInput() {
      $("#seating-plan-data").val(JSON.stringify(seatingData));
    }

    // Scale control: reads/writes the --sp-scale CSS variable that drives the
    // proportional (real-pixel) shrink of the boat layout. Kept in localStorage
    // so an operator's chosen zoom sticks across events, independent of any
    // individual event's saved plan.
    var SCALE_STORAGE_KEY = "pdsSeatingScale";
    var SCALE_MIN = 50;
    var SCALE_MAX = 100;

    function clampScale(pct) {
      if (isNaN(pct)) return 100;
      return Math.min(SCALE_MAX, Math.max(SCALE_MIN, pct));
    }

    function setupScaleControl() {
      var slider = $("#seating-scale");
      if (slider.length === 0) return;

      // Persist ONLY on a genuine user gesture. The browser's form-state
      // restoration can silently reset the slider on load AND fire a change
      // event; if we treated that as user input it would overwrite (corrupt)
      // the saved preference with the browser's remembered value. userTouched
      // is only flipped by real pointer/keyboard interaction with the slider.
      var userTouched = false;
      slider.on("pointerdown mousedown touchstart keydown", function () {
        userTouched = true;
      });

      slider.on("input change", function () {
        var pct = clampScale(parseInt($(this).val(), 10));
        applyScale(pct);
        if (userTouched) writeStoredScale(pct);
      });

      $("#seating-scale-reset").on("click", function () {
        slider.val(100);
        applyScale(100);
        writeStoredScale(100);
      });

      // localStorage is authoritative for the scale. Re-assert it on pageshow
      // as well as on first run, so neither the browser's form restoration nor
      // a bfcache restore can leave a stale scale on screen.
      function restoreScale() {
        var pct = clampScale(parseInt(readStoredScale(), 10));
        slider.val(pct);
        applyScale(pct);
      }
      restoreScale();
      $(window).on("pageshow", restoreScale);
    }

    function applyScale(pct) {
      var planner = document.getElementById("pds-seating-planner-app");
      if (planner) {
        planner.style.setProperty("--sp-scale", pct / 100);
      }
      $("#seating-scale-value").text(pct + "%");
    }

    function readStoredScale() {
      try {
        return window.localStorage.getItem(SCALE_STORAGE_KEY);
      } catch (e) {
        return null;
      }
    }

    function writeStoredScale(pct) {
      try {
        window.localStorage.setItem(SCALE_STORAGE_KEY, pct);
      } catch (e) {
        // Ignore storage failures (private mode, disabled storage, etc.)
      }
    }

    function loadSeatingArrangement() {
      // Load seating data for each boat
      for (
        var boatIndex = 0;
        boatIndex < seatingData.boats.length;
        boatIndex++
      ) {
        if (seatingData.boats[boatIndex]) {
          $.each(seatingData.boats[boatIndex], function (position, userData) {
            var positionElement = $(
              '.position[data-position="' +
                position +
                '"][data-boat="' +
                boatIndex +
                '"]'
            );
            if (positionElement.length && userData) {
              positionElement.html(
                '<span class="assigned-paddler draggable" data-user-id="' +
                  userData.userId +
                  '"><span class="paddler-name">' +
                  userData.userName +
                  '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>'
              );
              positionElement.addClass("position-filled");

              // Make assigned paddler draggable
              var assignedPaddlerElement =
                positionElement.find(".assigned-paddler");
              assignedPaddlerElement.draggable({
                revert: "invalid",
                helper: "clone",
                zIndex: 1000,
                scroll: false,
                distance: 5,
                delay: 100,
              });

              // Remove this paddler from available list
              $(
                '.paddler-item[data-user-id="' + userData.userId + '"]'
              ).remove();
            }
          });
        }
      }
    }
  });
})(jQuery);
