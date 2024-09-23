(function(angular, $, _) {
  angular.module("mjwshared").config(function($routeProvider) {
    $routeProvider.when("/paymentprocessorWebhook", {
      controller: "MjwsharedPaymentprocessorWebhook",
      controllerAs: "$ctrl",
      templateUrl: "~/mjwshared/PaymentprocessorWebhook.html",

      // If you need to look up data when opening the page, list it out
      // under "resolve".
      resolve: {
        processors: function(crmApi4) {
          return crmApi4("PaymentProcessor", "get", {
            select: [
              "id",
              "MIN(name) AS processorName",
              "MIN(payment_processor_type_id:label) AS processorType",
              "MIN(is_test) AS isTest",
              "COUNT(paymentprocessor_webhook.id) AS webhooksCount"
            ],
            join: [
              [
                "PaymentprocessorWebhook AS paymentprocessor_webhook",
                "LEFT",
                ["id", "=", "paymentprocessor_webhook.payment_processor_id"]
              ]
            ],
            groupBy: ["id"],
            where: [["is_test", "IS NOT EMPTY"]],
            orderBy: {
              "payment_processor_type_id:label": "ASC",
              is_test: "ASC"
            }
          });
        }
      }
    });
  });

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular
    .module("mjwshared")
    .controller("MjwsharedPaymentprocessorWebhook", function(
      $scope,
      crmApi4,
      crmStatus,
      crmUiHelp,
      processors
    ) {
      // The ts() and hs() functions help load strings for this module.
      var ts = ($scope.ts = CRM.ts("mjwshared"));
      var hs = ($scope.hs = crmUiHelp({
        file: "CRM/mjwshared/PaymentprocessorWebhook"
      })); // See: templates/CRM/mjwshared/PaymentprocessorWebhook.hlp
      // Local variable for this controller (needed when inside a callback fn where `this` is not available).
      var ctrl = this;

      this.processors = processors;
      this.events = [];
      this.statusFilter = "";
      this.processorFilter = "";
      this.eventFilter = "";
      this.rawFilter = "";
      this.identifierFilter = "";
      this.paymentProcessor = [];
      this.offset = 0;
      this.limit = 25;
      this.selectedRow = null;
      this.resultsCount = 0;
      this.lastQuery = "";

      this.statusMap = {
        new: {
          content: "🔵 " + ts("New"),
          title: ts("This event is awaiting processing")
        },
        processing: {
          content: "🟡 " + ts("Processing"),
          title: ts(
            "This event is currently being processed by the Scheduled Job"
          )
        },
        success: {
          content: "🟢 " + ts("Success"),
          title: ts("This event was successfully processed.")
        },
        error: {
          content: "🔴 " + ts("Error"),
          title: ts("There was an error processing this event.")
        }
      };

      this.abbreviate = function(text) {
        if (text === null) return "";
        return text.replace(/^(.{80}).+$/s, "$1 ...");
      };

      this.load = function() {
        const params = {
          select: [
            "*",
            "row_count",
            "payment_processor.name",
            "payment_processor.is_test"
          ],
          join: [
            [
              "PaymentProcessor AS payment_processor",
              true,
              null,
              ["payment_processor_id", "=", "payment_processor.id"]
            ]
          ],
          where: [],
          orderBy: { id: "DESC" }
        };
        if (ctrl.statusFilter) {
          params.where.push(["status", "=", ctrl.statusFilter]);
        }
        if (ctrl.processorFilter) {
          params.where.push([
            "payment_processor_id",
            "=",
            ctrl.processorFilter
          ]);
        }
        if (ctrl.eventFilter) {
          params.where.push(["event_id", "LIKE", "%" + ctrl.eventFilter + "%"]);
        }
        if (ctrl.identifierFilter) {
          params.where.push([
            "identifier",
            "LIKE",
            "%" + ctrl.identifierFilter + "%"
          ]);
        }
        if (ctrl.rawFilter) {
          params.where.push(["data", "LIKE", "%" + ctrl.rawFilter + "%"]);
        }
        // If we've changed the query, then start from the top again.
        if (JSON.stringify(params) !== ctrl.lastQuery) {
          ctrl.offset = 0;
          ctrl.lastQuery = JSON.stringify(params);
        }

        // Handle paging.
        Object.assign(params, {
          offset: ctrl.offset,
          limit: ctrl.limit
        });

        return crmStatus(
          // Status messages. For defaults, just use "{}"
          { start: ts("Loading..."), success: ts("Loaded") },
          crmApi4("PaymentprocessorWebhook", "get", params).then(r => {
            ctrl.resultsCount = r.count;
            ctrl.events = r.map(row => {
              // See if we can pretty up the raw data.
              if (row.data) {
                try {
                  const parsed = JSON.parse(row.data);
                  if (parsed) {
                    row.data = JSON.stringify(parsed, null, 2);
                  }
                } catch (e) {}
              }
              return row;
            });
          })
        );
      };

      this.changePage = function(dir) {
        let newOffset = Math.min(
          Math.max(ctrl.offset + dir * ctrl.limit, 0),
          ctrl.resultsCount - 1
        );
        // console.log({newOffset, o: ctrl.offset, dir});
        if (newOffset != ctrl.offset) {
          ctrl.offset = newOffset;
          ctrl.load();
        }
      };

      this.delete = function(id) {
        if (!(parseInt(id) > 0)) return;
        if (
          !confirm(
            ts(
              "Deleting a received webhook event is not un-do-able, and you may not be able to generate it again. Are you sure?"
            )
          )
        ) {
          return;
        }

        return crmStatus(
          { start: ts("Deleting..."), success: ts("Gone") },
          crmApi4("PaymentprocessorWebhook", "delete", {
            where: [["id", "=", id]],
            limit: 1
          })
        ).then(r => {
          // Reload the page.
          return ctrl.load();
        });
      };

      this.retry = function(id) {
        if (!(parseInt(id) > 0)) return;
        if (
          !confirm(
            ts(
              "Retrying an event could cause bad things to happen, depending on the event and the processor, so please be confident in your understanding of both. Schedule retry of this event?"
            )
          )
        ) {
          return;
        }

        return crmStatus(
          // Status messages. For defaults, just use "{}"
          { start: ts("Updating..."), success: ts("Updated") },
          crmApi4("PaymentprocessorWebhook", "update", {
            where: [["id", "=", id]],
            values: {
              status: "new",
              processed_date: null,
              message: ts("Scheduled for retry")
            },
            limit: 1
          })
        ).then(r => {
          // Reload the page.
          return ctrl.load();
        });
      };

      this.load();
    });
})(angular, CRM.$, CRM._);
