import Alpine from "@alpinejs/csp";
import { libraryPage } from "./library";
import { cinemaPlayer } from "./player";
import { installRemoteNavigation } from "./remote";

Alpine.data("libraryPage", libraryPage);
Alpine.data("cinemaPlayer", cinemaPlayer);
installRemoteNavigation();
window.Alpine = Alpine;
Alpine.start();
