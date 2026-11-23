import Alpine from "@alpinejs/csp";
import { libraryPage } from "./library";
import { cinemaPlayer } from "./player";
import { installRemoteNavigation } from "./remote";
import { installPressFeedback } from "./feedback";

Alpine.data("libraryPage", libraryPage);
Alpine.data("cinemaPlayer", cinemaPlayer);
installRemoteNavigation();
installPressFeedback();
window.Alpine = Alpine;
Alpine.start();
