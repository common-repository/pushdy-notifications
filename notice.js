jQuery(document).ready(notice);

var state = {
    post_id : ajax_object.post_id,  // post id sent from php backend
    first_modified : undefined,     // when the post was first modified
    started : false,                // post notification requests started
    interval: undefined,            // global interval for reattempting requests
    interval_count : 0,             // how many times has the request been attempted
    status : undefined              // whether the post is scheduled or published
  }
    
function notice() {
  if (!isWpCoreEditorDefined()) {
    return;
  }

  const editor = wp.data.select("core/editor");
  const get_wp_attr = attr => {
    return editor.getEditedPostAttribute(attr);
  };

  /*
   * Subscribes function to WP's state-change listener
   *  - checks change in post modified date
   *  - triggers interval that checks if recipient meta data available in backend
   */
  wp.data.subscribe(() => {
    // runs with each change in wp state
    const post = editor.getCurrentPost();

    // runs until post data loads
    if(!post || post === {}){
      return;
    }

    // post is defined now 
    if (!state.first_modified) {
      // captures last modified date of loaded post
      state.first_modified = post.modified;	
    }

    // latest modified date, status of the post
    const { modified, status } = post;
    state.status = status;

    // is checked
    const send_os_notif = jQuery("[name=send_pushdy_notification]").attr(
      "checked"
    );

    // if last modified differs from first modified times, post_modified = true
    const post_modified = modified !== state.first_modified;

    const is_published = status === "publish";
    const is_scheduled = status === "future"; 

    // if hasn't started, change detected, box checked, and the status is 'publish'
    if (!state.started && post_modified && send_os_notif && (is_published || is_scheduled)) {
      state.interval = setInterval(get_metadata, 3000); // starts requests
      state.started = true;
    }
  });

  const get_metadata = () => {
    const data = {
      action: "has_metadata",
      post_id: state.post_id
    };

    jQuery.get(ajax_object.ajax_url, data, function(response) {
      response = JSON.parse(response);
      const { status_code, response_body } = response;

      if(window.DEBUG_MODE){
        console.log(response);
      }

      const is_status_empty = status_code.length == 0;

      if(!is_status_empty){
        // status 0: HTTP request failed
        if (status_code === "0") {
          error_notice("Pushdy Push: request failed with status code 0. "+response_body);
          reset_state();
          return;
        }

        // 400 & 500 level errors
        if (status_code >= 400) {
          if (!response_body) {
            error_notice(
              "Pushdy Push: there was a " +
                status_code +
                " error sending your notification"
            );
          } else {
            error_notice("Pushdy Push: there was a " + status_code + " error sending your notification: " + response_body);
          }

          reset_state();
          return;
        }

        show_notice(0);
        reset_state();
      }
    });

    // try for 1 minute (each interval = 3s)
    if (state.interval_count > 20) {
      error_notice(
        "Pushdy Push: Did not receive a response status from last notification sent"
      );
      reset_state();
    }
    
    state.interval_count += 1;
  };

  /*
   * Gets recipient count and shows notice
   */
  const show_notice = recipients => {
    
    if (state.status === "publish") {
      var notice_text = "Pushdy Push: Successfully sent a notification to recipients";
    } else if (state.status === "future"){
      var notice_text = "Pushdy Push: Successfully scheduled a notification for recipients";
    }

    wp.data
      .dispatch("core/notices")
      .createNotice(
        "info",
        notice_text +
          ". Go to your app's \"Delivery\" tab to check sent and scheduled messages: https://dashboard.pushdy.com/#/application",
        {
            id:'pushdy-notice',
            isDismissible: true
        }
      );
  };

  const error_notice = error => {
    wp.data.dispatch("core/notices").createNotice("error", error, {
        isDismissible: true,
        id:'pushdy-error'
    });
  };

  const reset_state = () => {
    clearInterval(state.interval);
    state.interval = undefined;
    state.interval_count = 0;
    state.started = false;
    state.first_modified = undefined;
  }
};

const isWpCoreEditorDefined = () => {
  var unloadable = ""; // variable name that couldn't be loaded
  if (!wp || !wp.data || !wp.data.select("core/editor")) {
    if (!wp) {
      unloadable = "wp";
    } else if (!wp.data) {
      unloadable = "wp.data";
    } else if (!wp.data.select("core/editor")) {
      unloadable = 'wp.data.select("core/editor")';
    }

    console.warn(
      `Pushdy Push: could not load ${unloadable}.`
    );
    return false;
  } else {
    return true;
  }
};

/**
 * - use the debug method in the console to show data about the request
 * - works in Gutenberg editor
 *
 * returns an object in the format
 *  { success : "true",
 *    recipients : "1374",
 *    response_body : []
 *  }
 */
window.Pushdy = {
    debug : () => {
        window.DEBUG_MODE = window.DEBUG_MODE ? !window.DEBUG_MODE : true;
        notice();
    }
};
