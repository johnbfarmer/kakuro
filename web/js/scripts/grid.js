var Grid = React.createClass({
    getInitialState: function() {
        return { cells: [], height: 0, width: 0, active_row: 1, active_col: 2, cell_choices: [] };
    },
    componentDidMount: function() {
        // console.log(this.getDOMNode());
        this.getGrid();
    },
    getGrid: function() {
            console.log(this.props.filename);
        return $.getJSON(
            // "http://kak.uro/app_dev.php/api/grid/medium1.kak"
            "http://kak.uro/app_dev.php/api/grid/" + this.props.filename
        ).then(data => {
            // console.log(data);
            this.setState({ cells: data.cells, height: data.height, width: data.width });
            // console.log(this.state.cells);
        });
    },
    setActive: function(row, col) {
        this.setState({active_row: row, active_col: col});
    },
    render: function() {
        var cells = this.state.cells.map(function(cell, index) {
            var choices = this.state.cell_choices[index];
            var col = index % this.state.width;
            var row = Math.floor(index / this.state.width);
            var active = row === this.state.active_row && col === this.state.active_col;
            return <Cell cell={cell} choices={choices} key={index} row={row} col={col} active={active} onClick={() => this.setActive(row, col)} />;
        }, this);
        return (
            <div className="kakuro-grid">
               {cells}
            </div>
        );
    }
});

var Cell = React.createClass({
    getInitialState: function() {
        var editable = this.props.row > 0 && this.props.col > 0 && this.props.cell == null;
        var blank = !$.isArray(this.props.cell);
        if (!blank) {
            var leftText = this.props.cell[0] ? this.props.cell[0] : "";
            var rightText = this.props.cell[1] ? this.props.cell[1] : "";
        }
        var txt = blank ? "" : leftText + "\\" + rightText;
        return { 
            cell: txt, 
            choices: this.props.choices,
            editable: editable, 
            active: this.props.active, 
            row: this.props.row,
            col: this.props.col
        };
    },
    getClasses: function() {
        var classes = "kakuro-cell";
        if (!this.state.editable) {
            classes = classes + " blnk";
        }
        if (this.props.active) {
            classes = classes + " red";
        }
        if (this.props.col === 0) {
            classes = classes + " clr";
        }
        return classes;
    },
    setActive: function() {
        if (this.state.editable) {
            this.props.onClick();
        }
    },
    handleKeyDown: function(event) {
        console.log(event.key);
    },
    render: function() {
        if (this.props.active) {
            return (
                <div className={this.getClasses()}>
                    <input type="text" onKeyDown={this.handleKeyDown} />
                </div>
            );
        }
        return (
            <div className={this.getClasses()} onClick={() => this.setActive()}>
                {this.state.cell}
            </div>
        );
    }
});

ReactDOM.render(<Grid filename={filename}/>, document.getElementById("content"));
