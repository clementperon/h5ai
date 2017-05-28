<?php

class Api {

    private $app;


    public function __construct($app) {

        $this->app = $app;
    }


    public function apply() {

        $action = Util::query_request_param("action");
        $supported = ["download", "get", "login", "logout", "delete", "upload", "rename"];
        Util::json_fail(Util::ERR_UNSUPPORTED, "unsupported action", !in_array($action, $supported));

        $methodname = "on_${action}";
        $this->$methodname();
    }


    private function on_download() {

        Util::json_fail(Util::ERR_DISABLED, "download disabled", !$this->app->query_option("download.enabled", false));

        $as = Util::query_request_param("as");
        $type = Util::query_request_param("type");
        $base_href = Util::query_request_param("baseHref");
        $hrefs = Util::query_request_param("hrefs");

        $archive = new Archive($this->app);

        set_time_limit(0);
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$as\"");
        header("Connection: close");
        $ok = $archive->output($type, $base_href, $hrefs);

        Util::json_fail(Util::ERR_FAILED, "packaging failed", !$ok);
        exit;
    }


    private function on_get() {

        $response = [];

        foreach (["langs", "options", "setup", "types"] as $name) {
            if (Util::query_boolean_request_param($name, false)) {

                $methodname = "get_${name}";
                $response[$name] = $this->app->$methodname();
            }
        }

        if (Util::query_boolean_request_param("theme", false)) {

            $theme = new Theme($this->app);
            $response["theme"] = $theme->get_icons();
        }

        if (Util::query_request_param("items", false)) {

            $href = Util::query_request_param("items.href");
            $what = Util::query_numeric_request_param("items.what");

            $response["items"] = $this->app->get_items($href, $what);
        }

        if (Util::query_request_param("custom", false)) {

            Util::json_fail(Util::ERR_DISABLED, "custom disabled", !$this->app->query_option("custom.enabled", false));
            $href = Util::query_request_param("custom");

            $custom = new Custom($this->app);
            $response["custom"] = $custom->get_customizations($href);
        }

        if (Util::query_request_param("l10n", false)) {

            Util::json_fail(Util::ERR_DISABLED, "l10n disabled", !$this->app->query_option("l10n.enabled", false));
            $iso_codes = Util::query_array_request_param("l10n");

            $iso_codes = array_filter($iso_codes);
            $response["l10n"] = $this->app->get_l10n($iso_codes);
        }

        if (Util::query_request_param("search", false)) {

            Util::json_fail(Util::ERR_DISABLED, "search disabled", !$this->app->query_option("search.enabled", false));
            $href = Util::query_request_param("search.href");
            $pattern = Util::query_request_param("search.pattern");

            $search = new Search($this->app);
            $response["search"] = $search->get_items($href, $pattern);
        }

        if (Util::query_request_param("thumbs", false)) {

            Util::json_fail(Util::ERR_DISABLED, "thumbnails disabled", !$this->app->query_option("thumbnails.enabled", false));
            Util::json_fail(Util::ERR_UNSUPPORTED, "thumbnails not supported", !HAS_PHP_JPEG);
            $thumbs = Util::query_array_request_param("thumbs");

            $response["thumbs"] = $this->app->get_thumbs($thumbs);
        }

        Util::json_exit($response);
    }


    private function on_login() {

        $pass = Util::query_request_param("pass");
        $_SESSION[AS_ADMIN_SESSION_KEY] = strcasecmp(hash("sha512", $pass), PASSHASH) === 0;
        Util::json_exit(["asAdmin" => $_SESSION[AS_ADMIN_SESSION_KEY]]);
    }


    private function on_logout() {

        $_SESSION[AS_ADMIN_SESSION_KEY] = false;
        Util::json_exit(["asAdmin" => $_SESSION[AS_ADMIN_SESSION_KEY]]);
    }


    private function on_delete() {

        Util::json_fail(1, "deletion disabled", !$this->options["delete"]["enabled"]);

        $hrefs = Util::use_request_param("hrefs");

        $hrefs = explode("|:|", trim($hrefs));
        $errors = array();

        foreach ($hrefs as $href) {

        $d = Util::normalize_path(dirname($href), true);
        $n = basename($href);

            if ($this->app->is_managed_url($d) && !$this->app->is_hidden($n)) {

                $path = $this->app->to_path($href);

                if (!Util::delete_path($path, true)) {
                    $errors[] = $href;
                }
            }
        }

        Util::json_fail(2, "deletion failed for some", count($errors) > 0);
        Util::json_exit();
    }


    private function on_upload() {

        Util::json_fail(1, "upload disabled", !$this->options["dropbox"]["enabled"]);

        $href = Util::use_request_param("href");

        Util::json_fail(2, "wrong HTTP method", strtolower($_SERVER["REQUEST_METHOD"]) !== "post");
        Util::json_fail(3, "something went wrong", !array_key_exists("userfile", $_FILES));

        $userfile = $_FILES["userfile"];

        Util::json_fail(4, "something went wrong [" . $userfile["error"] . "]", $userfile["error"] !== 0);
        Util::json_fail(5, "folders not supported", file_get_contents($userfile["tmp_name"]) === "null");

        $upload_dir = $this->app->to_path($href);

        Util::json_fail(6, "upload dir no h5ai folder or ignored", !$this->app->is_managed_url($href) || $this->app->is_hidden($upload_dir));

        $dest = $upload_dir . "/" . urldecode($userfile["name"]);

        Util::json_fail(7, "already exists", file_exists($dest));
        Util::json_fail(8, "can't move uploaded file", !move_uploaded_file($userfile["tmp_name"], $dest));
        Util::json_exit();
    }


    private function on_rename() {

        Util::json_fail(1, "renaming disabled", !$this->options["rename"]["enabled"]);

        $href = Util::use_request_param("href");
        $name = Util::use_request_param("name");

        $d = Util::normalize_path(dirname($href), true);
        $n = basename($href);

        if ($this->app->is_managed_url($d) && !$this->app->is_hidden($n)) {

        $path = $this->app->to_path($href);
        $folder = Util::normalize_path(dirname($path));

        if (!rename($path, $folder . "/" . $name)) {
        Util::json_fail(2, "renaming failed");
        }
        }

        Util::json_exit();
    }
}
