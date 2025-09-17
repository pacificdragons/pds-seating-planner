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
    if ($("#pds-seating-planner").length === 0) return;

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

    // Determine actual boat count based on user preference or default
    if (seatingData.metadata.boat2Visible === false) {
      actualBoatCount = 1; // User explicitly hid boat 2
    } else if (seatingData.metadata.boat2Visible === true) {
      actualBoatCount = 2; // User explicitly wants boat 2
    } else {
      actualBoatCount = defaultBoatCount; // Use default based on paddler count
    }

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

    function setupGlobalControls(count) {
      var globalControls = $("#global-controls");
      globalControls.empty();

      // Check if boat 2 is visible based on metadata
      var boat2Visible =
        seatingData.metadata && seatingData.metadata.boat2Visible;

      var templateId = boat2Visible
        ? "multi-boat-controls-template"
        : "single-boat-controls-template";
      var template = document.getElementById(templateId);
      var clone = document.importNode(template.content, true);

      globalControls.append(clone);
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

    // Add/Remove boat 2 button click handler
    $(document).on("click", "#add-remove-boat-2", function () {
      if (!seatingData.metadata.boat2Visible) {
        addBoat2();
      } else {
        removeBoat2();
      }
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

    function addBoat2() {
      // Update boat count
      boatCount = 2;
      window.boatCount = 2;

      // Set user preference to show boat 2
      seatingData.metadata.boat2Visible = true;

      // Ensure we have boat data arrays
      while (seatingData.boats.length < 2) {
        seatingData.boats.push({});
      }

      // Recreate both boats with proper titles (since we're going from 1 to 2 boats)
      // Store current seating data to restore after recreation
      var currentSeatingData = JSON.parse(JSON.stringify(seatingData.boats));

      // Clear and recreate all boats with titles
      $("#boats-container").empty();
      for (var i = 0; i < 2; i++) {
        var boatElement = createBoatFromTemplate(i, 2);
        $("#boats-container").append(boatElement);
      }

      // Re-initialize drag/drop for all boats
      initializeDragDrop();

      // Restore seating arrangement
      loadSeatingArrangement();

      // Update global controls
      setupGlobalControls(window.boatCount);

      updateSeatingDataInput();

      // Show feedback
      $("#save-status")
        .html(
          '<span style="color: #46b450;">✓ Boat 2 added successfully!</span>'
        )
        .addClass("success");
      setTimeout(function () {
        $("#save-status").fadeOut(300, function () {
          $(this).html("").show();
        });
      }, 2000);
    }

    function removeBoat2() {
      if (
        confirm(
          "Are you sure you want to remove Boat 2? This will move all assigned paddlers from Boat 2 back to the available list."
        )
      ) {
        // Move all paddlers from boat 2 back to available list
        $('.position-filled[data-boat="1"]').each(function () {
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

        // Remove boat 2 from DOM
        $('.boat-container[data-boat="1"]').remove();

        // Update boat count
        boatCount = 1;
        window.boatCount = 1;

        // Set user preference to hide boat 2
        seatingData.metadata.boat2Visible = false;

        // Clear boat 2 seating data
        if (seatingData.boats.length > 1) {
          seatingData.boats.splice(1, 1);
        }

        // Update global controls
        setupGlobalControls(window.boatCount);

        updateSeatingDataInput();

        // Show feedback
        $("#save-status")
          .html(
            '<span style="color: #46b450;">✓ Boat 2 removed successfully!</span>'
          )
          .addClass("success");
        setTimeout(function () {
          $("#save-status").fadeOut(300, function () {
            $(this).html("").show();
          });
        }, 2000);
      }
    }

    function updateSeatingDataInput() {
      $("#seating-plan-data").val(JSON.stringify(seatingData));
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
