import React from 'react';
import { ButtonGroup, Button, Glyphicon } from 'react-bootstrap';

export default class KakuroControls extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            savedGameName: '',
            grids: [],
            createMode: false,
            height: 4,
            width: 4,
        };

        this.loadVals(props);

        this.updateSavedGameName = this.updateSavedGameName.bind(this);
        this.changeGrid = this.changeGrid.bind(this);
        this.save = this.save.bind(this);
        this.saveCopy = this.saveCopy.bind(this);
        this.updateNewAttributes = this.updateNewAttributes.bind(this);
        this.newGrid = this.newGrid.bind(this);
        this.getSaveRow = this.getSaveRow.bind(this);
        this.designButton = this.designButton.bind(this);
    }

    componentDidMount() {}

    componentWillUpdate(props) {
        this.loadVals(props);
    }

    loadVals(props) {
        this.state.savedGameName = props.savedGameName.length > 0 ? props.savedGameName : props.gridName;
        this.state.height = props.height;
        this.state.width = props.width;
        this.state.grids = this.processDropdownOptions(props.grids);
        this.state.createMode = props.createMode || false;
    }

    updateSavedGameName(e) {
        var val = e.target.value;
        this.setState({savedGameName: val});
    }

    save() {
        this.props.save(this.state.savedGameName, false);
    }

    saveCopy() {
        this.props.save(this.state.savedGameName, true);
    }

    processDropdownOptions(data) {
        var arr = [];
        arr.push(<option key="topChoice" value={-1}>--select--</option>);
        if (this.props.createMode) {
            arr.push(<option key="newGrid" value={0}>new grid</option>);
        }
        for (var i = 0; i < data.length; i++) {
            var option = data[i];
            var label = 'label' in option ? option.label : option.name;
            var val = option.name;
            arr.push(<option key={i} value={val}>{label}</option>);
          }

        return arr;
    }

    changeGrid(e) {
        this.props.getGrid(e.target.value);
    }

    updateNewAttributes(e) {
        var val = e.target.value;
        var ds = e.target.dataset;
        var k = ds.newattr;
        if (k === 'height' || k === 'width') {
            val = parseInt(val);
        }
        var s = this.state;
        if (isNaN(val)) {val = '';}
        s[k] = val;
        this.setState(s);
    }

    newGrid(e) {
        console.log('new grid height '+this.state.height+' width '+this.state.width);
        this.props.newGrid(this.state.height, this.state.width);
    }

    getNewGridAttributes() {
        if (!this.state.createMode) {
            return '';
        }

        return (
            <div className="row">
                height: <input value={this.state.height} data-newattr="height" onChange={this.updateNewAttributes} />
                width: <input value={this.state.width} data-newattr="width" onChange={this.updateNewAttributes} />
                <ButtonGroup>
                    <Button onClick={this.newGrid} title="create">
                        <Glyphicon glyph="arrow-right" />
                    </Button>
                </ButtonGroup>
            </div>
        );
    }

    getCheckSolutionButtonGroup() {
        if (!this.state.createMode) {
            return '';
        }

        return (
            <div className="row">
                check solution:
                <ButtonGroup>
                    <Button onClick={this.props.checkSolution} title="check solution">
                        <Glyphicon glyph="ok" />
                    </Button>
                </ButtonGroup>
            </div>
        );
    }

    getSaveRow() {
        return (
            <div className="row">
                <input value={this.state.savedGameName} onChange={this.updateSavedGameName} />
                <ButtonGroup>
                    <Button onClick={this.save} title="save">
                        <Glyphicon glyph="floppy-disk" />
                    </Button>
                    <Button onClick={this.saveCopy} title="save copy">
                        <Glyphicon glyph="duplicate" />
                    </Button>
                </ButtonGroup>
            </div>
        );
    }

    designButton(id) {
        var url = 'http://kak.uro/app_dev.php/grid/design/' + id;
        return (
            <div className="row">
                <a href={url}>
                    <Glyphicon glyph="edit" />
                </a>
            </div>
        );
    }

    playButton(id) {
        var url = 'http://kak.uro/app_dev.php/grid/' + id;
        return (
            <div className="row">
                <a href={url}>
                    <Glyphicon glyph="play" />
                </a>
            </div>
        );
    }

    render() {
        var newGridAttributes = this.getNewGridAttributes();
        var checkSolutionButtonGroup = this.getCheckSolutionButtonGroup();
        var saveRow = this.props.showSave ? this.getSaveRow() : '';
        var designRow = this.props.showDesign ? this.designButton(this.props.selectedGrid) : '';
        var playRow = this.props.showPlay ? this.playButton(this.props.selectedGrid) : '';
        return (
            <div>
                <div className="row">
                    <select onChange={this.changeGrid} value={this.props.selectedGrid}>
                        {this.state.grids}
                    </select>
                </div>
                {newGridAttributes}
                {checkSolutionButtonGroup}
                {saveRow}
                {designRow}
                {playRow}
            </div>
        );
    }
}

