#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

fn main() {
    std::panic::set_hook(Box::new(|info| {
        let msg = format!("ATOMA Windows Crash Report:\n{}\n", info);
        eprintln!("{}", msg);
        if let Some(mut path) = std::env::var_os("USERPROFILE").map(std::path::PathBuf::from) {
            path.push("Desktop");
            path.push("atoma_crash_log.txt");
            let _ = std::fs::write(&path, &msg);
        }
        if let Some(mut temp) = std::env::var_os("TEMP").map(std::path::PathBuf::from) {
            temp.push("atoma_crash_log.txt");
            let _ = std::fs::write(&temp, &msg);
        }
    }));

    atoma_lib::run();
}
