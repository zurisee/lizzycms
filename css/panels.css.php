<?php
header("Content-type: text/css; charset: UTF-8");

/*
**  Lizzy Panels: Styles
 */

$threshold = '480px';
if (isset($_GET['threshold'])) {
    $threshold = $_GET['threshold'];
}


$css = <<<EOT

/* All Modes */
.lzy-panels-widget,
.lzy-panels-widget  div {
    position: relative;
}

.lzy-panels-widget .lzy-panel-inner-wrapper,
.lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header[aria-selected=true] {
    z-index: 3;
}


/* Tabs Mode: */
.lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header {
    padding: 1em 2em 0.8em; /* space within tab */
    z-index: 0;
}

.lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panels-header-list {
    display: block;
    width: 100%;
    padding: 0;
    margin: 0;
}
li.lzy-tabs-mode-panel-header {
    margin: 0;
}

/* Tab: */
    .lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header {
        display: inline-block;
        position: relative;
        z-index: 0;
    }
    .lzy-panels-widget.lzy-tab-mode .lzy-panel-page .lzy-panel-inner-wrapper {
        margin-top: 0px!important;
        overflow: hidden;
    }
    div.lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header:first-child {
        margin-left: 10px;    /* space left of first tab */
    }

    .lzy-panels-widget.lzy-tab-mode li.lzy-tabs-mode-panel-header {
        border-bottom: none;
    }

    .lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header {
        cursor: pointer;
    }
    .lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header[aria-selected=true] {
        cursor: default;
    }
    .lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header[aria-selected=true]:hover {
        text-decoration:none;
    }
    .lzy-panels-widget.lzy-tab-mode .lzy-panel-page[aria-hidden=true] {
        display: none;
    }
    .lzy-tabs-mode-panel-header::before {
        content: '';
        position: absolute;
        top: 0; right: 0;bottom: 0; left: 0;
        background: transparent;
        border-bottom: none!important;
        border-top-right-radius: 7px;
        border-top-left-radius: 7px;
        z-index: -1;
    }

/* Tilted Tabs: */
    .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        z-index: -1;
        border-bottom: none;
        border-radius: .3em .3em 0 0;
        transform: perspective(1.1em) rotateX( 3deg);
        transform-origin: bottom;
    }


/* Panel-body: */
    .lzy-panel-body-wrapper::before {   /* for outline of panel body */
        content: '';
        position: absolute;
        top: 0; right: 0;bottom: 0; left: 0;
        background: transparent;
        z-index: 0;
    }
    .lzy-tabs-mode-panels-header-list .lzy-panel-body-wrapper::before {
        border: none;
        outline: 1px solid transparent;
    }
    .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panels-header-list .lzy-tabs-mode-panel-header::after,
    .lzy-tabs-mode-panels-header-list .lzy-tabs-mode-panel-header::before {
        outline: none;
        border-bottom: 1px solid transparent;
    }

    .lzy-panel-body-wrapper::before {
        border: 1px solid transparent;
        outline: 1px solid transparent;
    }


/* Accordion Mode: */
.lzy-accordion-mode-panel-header,
.lzy-accordion-mode-panel-header * {
    margin: 0;
    padding: 0;
}

.lzy-panels-widget:not(.lzy-tab-mode) .lzy-tabs-mode-panels-header-list,
.lzy-tab-mode .lzy-accordion-mode-panel-header {
    display: none;
}

.lzy-panels-widget:not(.lzy-tab-mode) .lzy-accordion-mode-panel-header a {
    text-decoration: none;
    display: block;
}

.lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page .lzy-panel-body-wrapper {
    overflow: hidden;
}
.lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page .lzy-panel-inner-wrapper {
    margin-top: -100%;  /* initial value on the safe side, will be adjusted by js during initialization */
    overflow: auto;
    transition: margin-top 0.4s;
}

.lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page.lzy-panel-page-open .lzy-panel-inner-wrapper {
    margin-top: 0!important;
}
.lzy-accordion-mode-panel-header::before {
    position: absolute;
    content: '';
    top: 0; right: 0; bottom: 0; left: 0;
}

/* open/closed indicator in accordion header */
    .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page .lzy-accordion-mode-panel-header a::before {
        content: '►';
        margin-right: 0.4em;
        display: inline-block;
        transition: transform 0.3s;
    }

    .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page.lzy-panel-page-open .lzy-accordion-mode-panel-header a::before {
        transform: rotate(90deg);
        transition: transform 0.4s;
    }



/* === Widget Defaults ================================================== */
/* Tabs: */
        /* non-selected tabs: */
        .lzy-panels-widget.lzy-tab-mode .lzy-tabs-mode-panel-header {
            color: #ddd;
        }

        /* selected tab: */
        .lzy-panels-widget .lzy-tabs-mode-panel-header[aria-selected=true] {
            color: #444;
        }



/* Panel: */
        .lzy-panel-page {
            margin-bottom: 0.7em; /* space between collapsed accordion panels */
        }
        .lzy-panel-inner-wrapper {
            padding: 0.5em 1.2em;
        }


        /* Background unselected tabs: */
        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after,
        .lzy-tabs-mode-panel-header::before {
            background: #444;
        }

        /* Background: selected tab and panel*/
        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header[aria-selected=true]::after,
        .lzy-panels-widget .lzy-tabs-mode-panel-header[aria-selected=true]::before,
        .lzy-panel-body-wrapper,
        .lzy-panel-inner-wrapper {
            background: white;
        }

        /* Outline in tabs mode: */
        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after,
        .lzy-tabs-mode-panel-header::before,
        .lzy-panels-widget.lzy-tab-mode .lzy-panel-body-wrapper::before {
            border: 1px solid #444;
            outline-color: #444;
        }


        /* Shadow: */
/*
        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after,
        .lzy-tabs-mode-panel-header::before,
        .lzy-panel-body-wrapper::before {
            box-shadow: 0 0 10px gray;
        }
*/

        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header {
            margin-left: -0.5em;
        }
        .lzy-panels-widget.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::before {
            box-shadow: none;
            border: none;
            background: none;
        }


/* Accordion: */
        /* Size of accordion header */
        .lzy-accordion-mode-panel-header a {
            padding: 1em 1.2em;
            margin: 0;
        }
        /* Background of accordion-headers: */
        .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page {
            /*overflow: visible;*/
        }
        .lzy-accordion-mode-panel-header {
            background: #666;
            border: 1px solid #444;
        }
        /* Text in accordion-headers: */
        .lzy-accordion-mode-panel-header a {
            color: #ddd;
        }
        .lzy-accordion-mode-panel-header::before {
            position: absolute;
            content: '';
            top: 0; right: 0; bottom: 0; left: 0;
        }

        /* Outline of inner lzy-panel-page in accordion mode: */
/*        .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-inner-wrapper {
            border: 1px solid #505d00;
        }
*/

        /* Outline of outer lzy-panel-page in accordion mode */
        .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-body-wrapper::before {
            border: 1px solid #aaa;
        }

        /* Desktop mode: */
        @media screen and (min-width: $threshold) {
            .lzy-accordion-mode-panel-header a {
                padding: 0.7em 1.2em;
            }
            .lzy-accordion-mode-panel-header {
                border-radius: 5px;
            }
            .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-inner-wrapper {
                margin: -1px 10px 1px 10px;
                border-bottom-left-radius: 5px;
                border-bottom-right-radius: 5px;
            }
        }

        /* Open/closed symbol in accordion mode:  */
/*        .lzy-panels-widget:not(.lzy-tab-mode) .lzy-panel-page .lzy-accordion-mode-panel-header a::before {
            color: #ddd;
            text-shadow: 0px 0px 4px white;
        }
*/
EOT;

exit($css);
