//TEMPLATE
import { useContext } from "react";
import { ThemeContext } from "./ThemeContext";
import ThemeToggler from "./sidebar/ThemeToggler";
import UserMenu from "./UserMenu";
//Template
// The Profile / Log out menu lives in UserMenu.jsx so QDN pages (which
// render without this layout) can show the same menu in their BackLink row.
export default function NavBar() {
    const { theme, toggleTheme } = useContext(ThemeContext);

    return (
        <nav className="sticky top-0 z-50 bg-background/70 backdrop-blur-md h-14 shadow-lg">
            <div className="px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-end h-[54px] gap-3">
                    <ThemeToggler theme={theme} toggleTheme={toggleTheme} />

                    <div className="w-px h-5 bg-border" />

                    <UserMenu />
                </div>
            </div>
        </nav>
    );
}
